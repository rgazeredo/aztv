# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**AZ TV** é uma plataforma multi-tenant Laravel que serve como painel administrativo completo para gerenciamento de conteúdos, players Android, configurações de exibição e (futuramente) comunicação em tempo real com dispositivos.

### 🧩 Back-end Overview

- **Framework**: Laravel 12 com Inertia.js para funcionalidade SPA
- **Frontend**: React 19 com TypeScript, Tailwind CSS 4, e shadcn/ui components
- **Build Tools**: Vite com Laravel Wayfinder para rotas type-safe
- **Database**: SQLite (padrão), com Laravel Cashier para integração Stripe
- **Testing**: Pest PHP para testes de backend
- **Multi-tenant**: Separação lógica entre instâncias de clientes (tenants)

## Development Commands

**IMPORTANTE: Este projeto usa containers Docker. Todos os comandos PHP, Composer, NPM e Artisan devem ser executados dentro do container.**

### Container Commands
```bash
# Acessar o container
docker-compose exec app bash
# ou se estiver usando outro nome de serviço:
docker exec -it <container-name> bash
```

### Starting Development
```bash
# Full development environment (recommended) - DENTRO DO CONTAINER
composer dev
# This runs: server, queue, logs, and vite in parallel

# Alternative: SSR development - DENTRO DO CONTAINER
composer dev:ssr
```

### Building and Testing
```bash
# Frontend build - DENTRO DO CONTAINER
npm run build
npm run build:ssr  # For SSR

# Code quality - DENTRO DO CONTAINER
npm run lint        # ESLint with auto-fix
npm run types       # TypeScript type checking
npm run format      # Prettier formatting
npm run format:check

# Backend testing - DENTRO DO CONTAINER
composer test       # Runs PHP tests with Pest
```

### Laravel Commands
```bash
# Todos os comandos Artisan devem ser executados DENTRO DO CONTAINER
php artisan serve           # Start Laravel server
php artisan queue:listen    # Background jobs
php artisan pail           # Real-time logs
php artisan migrate        # Run migrations
php artisan make:migration  # Create migrations
php artisan make:model     # Create models
php artisan make:controller # Create controllers
```

## Architecture

### 📁 Estrutura Geral do Painel

#### 1. **Área Admin (Super Admin)**
- Gerenciar contas de clientes (tenants)
- Criar e editar planos (players e armazenamento)
- Ver estatísticas globais e status geral dos players
- Gerenciamento global da plataforma

#### 2. **Área do Cliente (Tenant)**
- Dashboard específico do tenant com métricas básicas
- Configurações de perfil e preferências
- Interface para futuras funcionalidades de mídia e players

### ⚙️ Multi-Tenancy Implementation

**Estrutura Implementada:**
- **Tenant Model**: Cada organização/empresa é um tenant com `slug`, `name`, `domain`
- **User Roles**: `admin` (global) e `client` (tenant-scoped)
- **Scoping**: `TenantScope` middleware para filtro automático de dados por tenant
- **Admin Access**: Usuários admin podem visualizar todos os tenants e alternar entre eles
- **Billing Integration**: Laravel Cashier integrado no modelo Tenant para gerenciamento de assinaturas

### Authentication & Authorization
- Sistema de autenticação Laravel completo com Inertia.js
- User model inclui campo `role` para distinção admin/client
- Relacionamento tenant: `User::belongsTo(Tenant::class)`
- Suporte a temas: usuários podem selecionar light/dark/system

### Subscription Management
**Implementado:**
- Integração Laravel Cashier para billing Stripe
- Workflow de registro com seleção de plano
- Fluxos de sucesso/cancelamento/retry de subscription
- Gerenciamento de planos através do `PricingController`
- Modelo `Subscription` para controle de assinaturas

### Frontend Structure
- **Components**: Localizados em `resources/js/components/` com shadcn/ui em `components/ui/`
- **Pages**: Páginas Inertia.js em `resources/js/pages/`
- **Layouts**: Layouts compartilhados em `resources/js/layouts/`
- **Routing**: Rotas type-safe geradas pelo Laravel Wayfinder
- **i18n**: Suporte à internacionalização com react-i18next (PT-BR e EN)

### Key Files Implemented
- `routes/web.php`: Definições principais de rotas
- `app/Http/Middleware/TenantScope.php`: Scoping de dados por tenant
- `app/Models/User.php`: Modelo User com relacionamentos de role e tenant
- `app/Models/Tenant.php`: Modelo Tenant com billing e funcionalidades multi-tenant
- `app/Models/Subscription.php`: Modelo para gerenciamento de assinaturas
- `resources/js/app.tsx`: Ponto de entrada da aplicação React
- `vite.config.ts`: Configuração de build com React, Tailwind, e Wayfinder

## Current Implementation Status

### ✅ **Já Implementado:**

#### Core Infrastructure
- ✅ Estrutura Laravel multi-tenant completa
- ✅ Sistema de autenticação com Laravel Breeze e Inertia
- ✅ Layout base do painel (admin + cliente) funcionando
- ✅ Middleware TenantScope para isolamento de dados
- ✅ Modelos User, Tenant, e Subscription configurados
- ✅ Integração completa com Stripe via Laravel Cashier
- ✅ Dashboard administrativo global
- ✅ Dashboard específico do cliente (tenant)

#### Frontend
- ✅ Landing page genérica com seções de pricing, features, contato
- ✅ Sistema de autenticação completo (login, registro, reset senha)
- ✅ Suporte multi-idiomas (PT-BR e EN)
- ✅ Design responsivo com Tailwind CSS
- ✅ Componentes shadcn/ui integrados
- ✅ Sistema de temas (light/dark/system)

#### Subscription & Billing
- ✅ Registro com seleção de plano
- ✅ Processamento de pagamentos via Stripe
- ✅ Páginas de sucesso e cancelamento
- ✅ Sistema de retry para pagamentos falhados

### 🚧 **Funcionalidades Planejadas (Não Implementadas):**

#### Gerenciamento de Mídia
- 📋 Upload direto pelo painel com validações
- 📋 Armazenamento em bucket S3-like (ex: Backblaze B2)
- 📋 Processamento com FFmpeg (compressão, transcodificação)
- 📋 Geração de thumbnails
- 📋 Organização por pastas, tags ou categorias

#### APK Player Integration
- 📋 Upload e gerenciamento de APKs
- 📋 API REST para ativação e sincronização dos players
- 📋 Sistema de códigos de ativação
- 📋 Download de APK customizado via link curto ou QR Code
- 📋 Gestão de players (apelido, local, última atividade)

#### Playlists & Scheduling
- 📋 Criação de playlists e agendamentos
- 📋 Configurações de looping e tempo entre mídias
- 📋 Agendamentos por horário, data ou recorrência

#### Real-time Communication
- 📋 WebSockets via Socket.io para comunicação bidirecional
- 📋 Comandos em tempo real (reiniciar, atualizar)
- 📋 Status atualizado automaticamente dos players

#### Analytics & Monitoring
- 📋 Estatísticas locais no player + sincronização
- 📋 Alertas por e-mail (player offline, erro de mídia)
- 📋 Logs e trilhas de auditoria

#### Content Features
- 📋 Módulo de conteúdo automático: clima, câmbio, frases
- 📋 Tabela de Preços (upload Excel + exibição personalizada)
- 📋 Sistema de templates customizáveis

## Database
- Default: SQLite (`database/database.sqlite`)
- Suporte MySQL/PostgreSQL via configuração de ambiente
- Queue jobs armazenados no banco
- Session storage no banco
- Migrations configuradas para users, tenants, subscriptions

## Environment Setup
- Copie `.env.example` para `.env`
- Configure settings do banco (SQLite por padrão)
- Configure chaves Stripe para funcionalidades de subscription
- Configure settings de email para notificações

## Monetização Implementada
- ✅ Controle de plano por tenant com limites configuráveis
- ✅ Sistema de billing via Stripe
- ✅ Trial period support
- ✅ Subscription status tracking
- 📋 Limites de players e armazenamento (estrutura pronta, lógica a implementar)

## Next Development Priorities

1. **Media Management System**: Implementar upload, storage e organização de mídias
2. **Player Management**: Criar sistema de registro e gestão de players Android
3. **API for Players**: Desenvolver endpoints REST para sincronização com players
4. **Real-time Communication**: Implementar WebSockets para comunicação bidirecional
5. **Analytics Dashboard**: Criar sistema de métricas e monitoramento

## Project Architecture Benefits
- ✅ Multi-tenancy architecture pronta e testada
- ✅ Subscription billing integrado e funcional
- ✅ Autenticação & autorização completa
- ✅ Frontend moderno React + TypeScript
- ✅ Sistema de design responsivo
- ✅ Suporte à internacionalização
- ✅ Ferramentas de desenvolvimento configuradas
- ✅ Base sólida para expansão com funcionalidades específicas de TV/mídia

## Task Master AI Instructions
**Import Task Master's development workflow commands and guidelines, treat as if import is in the main CLAUDE.md file.**
@./.taskmaster/CLAUDE.md
