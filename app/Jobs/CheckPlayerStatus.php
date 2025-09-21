<?php

namespace App\Jobs;

use App\Models\Player;
use App\Models\PlayerAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Mail\PlayerOfflineAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Exception;

class CheckPlayerStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180; // 3 minutes
    public int $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $tenantId = null,
        public bool $force = false
    ) {
        $this->onQueue('alerts');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting CheckPlayerStatus job', [
                'tenant_id' => $this->tenantId,
                'force' => $this->force,
            ]);

            $offlineTimeoutMinutes = Config::get('players.offline_timeout_minutes', 5);
            $alertThrottleMinutes = Config::get('players.alert_throttle_minutes', 30);

            // Get tenants to check
            $tenants = $this->tenantId
                ? Tenant::where('id', $this->tenantId)->get()
                : Tenant::all();

            $totalOfflinePlayers = 0;
            $totalAlertsCreated = 0;
            $totalEmailsSent = 0;

            foreach ($tenants as $tenant) {
                $result = $this->checkTenantPlayers(
                    $tenant,
                    $offlineTimeoutMinutes,
                    $alertThrottleMinutes
                );

                $totalOfflinePlayers += $result['offline_count'];
                $totalAlertsCreated += $result['alerts_created'];
                $totalEmailsSent += $result['emails_sent'];
            }

            Log::info('CheckPlayerStatus job completed', [
                'tenants_checked' => $tenants->count(),
                'offline_players' => $totalOfflinePlayers,
                'alerts_created' => $totalAlertsCreated,
                'emails_sent' => $totalEmailsSent,
            ]);

        } catch (Exception $e) {
            Log::error('CheckPlayerStatus job failed: ' . $e->getMessage(), [
                'tenant_id' => $this->tenantId,
                'exception' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Check players for a specific tenant
     */
    private function checkTenantPlayers(
        Tenant $tenant,
        int $offlineTimeoutMinutes,
        int $alertThrottleMinutes
    ): array {
        $offlineCount = 0;
        $alertsCreated = 0;
        $emailsSent = 0;

        // Get offline timeout timestamp
        $offlineThreshold = now()->subMinutes($offlineTimeoutMinutes);

        // Find players that are offline
        $offlinePlayers = Player::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where(function ($query) use ($offlineThreshold) {
                $query->where('last_seen_at', '<', $offlineThreshold)
                      ->orWhere(function ($q) use ($offlineThreshold) {
                          $q->whereNull('last_seen_at')
                            ->where('updated_at', '<', $offlineThreshold);
                      });
            })
            ->get();

        $offlineCount = $offlinePlayers->count();

        if ($offlineCount === 0) {
            Log::debug("No offline players found for tenant {$tenant->id}");
            return compact('offlineCount', 'alertsCreated', 'emailsSent');
        }

        Log::info("Found {$offlineCount} offline players for tenant {$tenant->id}");

        // Check each offline player for alerts
        foreach ($offlinePlayers as $player) {
            // Skip if recent alert exists and not forcing
            if (!$this->force && PlayerAlert::hasRecentAlert($player, $alertThrottleMinutes)) {
                Log::debug("Skipping recent alert for player {$player->id}");
                continue;
            }

            // Create alert
            $alert = PlayerAlert::createOfflineAlert($player);
            $alertsCreated++;

            Log::info("Created offline alert for player {$player->id}", [
                'player_name' => $player->name,
                'last_seen' => $player->last_seen_at,
                'alert_id' => $alert->id,
            ]);

            // Send email notifications
            $emailResult = $this->sendOfflineNotification($tenant, $player, $alert);
            $emailsSent += $emailResult;
        }

        return compact('offlineCount', 'alertsCreated', 'emailsSent');
    }

    /**
     * Send offline notification emails
     */
    private function sendOfflineNotification(Tenant $tenant, Player $player, PlayerAlert $alert): int
    {
        try {
            // Get admin users for this tenant
            $adminUsers = User::where('tenant_id', $tenant->id)
                ->where('role', 'admin')
                ->where('email_verified_at', '!=', null)
                ->get();

            if ($adminUsers->isEmpty()) {
                Log::warning("No admin users found for tenant {$tenant->id}");
                return 0;
            }

            $emailsSent = 0;

            foreach ($adminUsers as $admin) {
                try {
                    Mail::to($admin->email)->send(new PlayerOfflineAlert($player, $alert, $tenant));
                    $emailsSent++;

                    Log::info("Sent offline alert email", [
                        'player_id' => $player->id,
                        'player_name' => $player->name,
                        'admin_email' => $admin->email,
                        'tenant_id' => $tenant->id,
                        'alert_id' => $alert->id,
                    ]);

                } catch (Exception $e) {
                    Log::error("Failed to send alert email to {$admin->email}: " . $e->getMessage(), [
                        'player_id' => $player->id,
                        'admin_id' => $admin->id,
                        'tenant_id' => $tenant->id,
                    ]);
                }
            }

            return $emailsSent;

        } catch (Exception $e) {
            Log::error("Failed to send offline notifications: " . $e->getMessage(), [
                'player_id' => $player->id,
                'tenant_id' => $tenant->id,
            ]);

            return 0;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('CheckPlayerStatus job failed permanently: ' . $exception->getMessage(), [
            'tenant_id' => $this->tenantId,
            'force' => $this->force,
            'exception' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        $tags = ['player-monitoring', 'alerts'];

        if ($this->tenantId) {
            $tags[] = 'tenant:' . $this->tenantId;
        }

        if ($this->force) {
            $tags[] = 'forced';
        }

        return $tags;
    }
}