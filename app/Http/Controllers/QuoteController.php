<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Exception;

class QuoteController extends Controller
{
    protected QuoteService $quoteService;

    public function __construct(QuoteService $quoteService)
    {
        $this->quoteService = $quoteService;
    }

    public function index(Request $request)
    {
        $tenant = $this->getCurrentTenant($request);

        $query = Quote::forTenant($tenant->id)->with('tenant');

        if ($request->has('category') && $request->category !== 'all') {
            $query->byCategory($request->category);
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('text', 'LIKE', "%{$search}%")
                  ->orWhere('author', 'LIKE', "%{$search}%");
            });
        }

        $quotes = $query->orderBy('created_at', 'desc')
                       ->paginate(15)
                       ->withQueryString();

        $statistics = $this->quoteService->getStatistics($tenant);
        $categories = Quote::getAvailableCategories();

        return Inertia::render('Quotes/Index', [
            'quotes' => $quotes,
            'statistics' => $statistics,
            'categories' => $categories,
            'filters' => $request->only(['category', 'status', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $this->getCurrentTenant($request);

        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:500',
            'author' => 'nullable|string|max:100',
            'category' => 'required|in:motivacional,inspiracional,empresarial,sucesso,liderança',
            'display_duration' => 'integer|min:10|max:300',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $quote = Quote::create([
                'tenant_id' => $tenant->id,
                'text' => $request->text,
                'author' => $request->author,
                'category' => $request->category,
                'display_duration' => $request->display_duration ?? 30,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->quoteService->clearCache($tenant);

            Log::info('Quote created successfully', [
                'quote_id' => $quote->id,
                'tenant_id' => $tenant->id,
            ]);

            return response()->json([
                'success' => true,
                'quote' => $quote,
                'message' => 'Frase criada com sucesso',
            ], 201);

        } catch (Exception $e) {
            Log::error('Failed to create quote', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao criar frase',
            ], 500);
        }
    }

    public function show(Quote $quote)
    {
        return response()->json([
            'success' => true,
            'quote' => $quote->load('tenant'),
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        $tenant = $this->getCurrentTenant($request);

        if ($quote->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Não autorizado',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:500',
            'author' => 'nullable|string|max:100',
            'category' => 'required|in:motivacional,inspiracional,empresarial,sucesso,liderança',
            'display_duration' => 'integer|min:10|max:300',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $quote->update([
                'text' => $request->text,
                'author' => $request->author,
                'category' => $request->category,
                'display_duration' => $request->display_duration,
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->quoteService->clearCache($tenant);

            Log::info('Quote updated successfully', [
                'quote_id' => $quote->id,
                'tenant_id' => $tenant->id,
            ]);

            return response()->json([
                'success' => true,
                'quote' => $quote->fresh(),
                'message' => 'Frase atualizada com sucesso',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to update quote', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao atualizar frase',
            ], 500);
        }
    }

    public function destroy(Quote $quote, Request $request)
    {
        $tenant = $this->getCurrentTenant($request);

        if ($quote->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Não autorizado',
            ], 403);
        }

        try {
            $quote->delete();

            $this->quoteService->clearCache($tenant);

            Log::info('Quote deleted successfully', [
                'quote_id' => $quote->id,
                'tenant_id' => $tenant->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Frase excluída com sucesso',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to delete quote', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao excluir frase',
            ], 500);
        }
    }

    public function toggle(Quote $quote, Request $request)
    {
        $tenant = $this->getCurrentTenant($request);

        if ($quote->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Não autorizado',
            ], 403);
        }

        try {
            $quote->update([
                'is_active' => !$quote->is_active,
            ]);

            $this->quoteService->clearCache($tenant);

            Log::info('Quote status toggled', [
                'quote_id' => $quote->id,
                'new_status' => $quote->is_active ? 'active' : 'inactive',
                'tenant_id' => $tenant->id,
            ]);

            return response()->json([
                'success' => true,
                'quote' => $quote->fresh(),
                'message' => $quote->is_active ? 'Frase ativada' : 'Frase desativada',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to toggle quote status', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao alterar status da frase',
            ], 500);
        }
    }

    public function preview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:500',
            'author' => 'nullable|string|max:100',
            'category' => 'required|in:motivacional,inspiracional,empresarial,sucesso,liderança',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'preview' => [
                'text' => $request->text,
                'author' => $request->author,
                'category' => $request->category,
                'category_label' => Quote::getAvailableCategories()[$request->category] ?? '',
            ],
        ]);
    }

    private function getCurrentTenant(Request $request): Tenant
    {
        return $request->user()->tenant ?? Tenant::first();
    }
}
