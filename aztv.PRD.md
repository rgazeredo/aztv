# 📄 PRD — Plataforma AZ TV (Painel Administrativo + Backend)

## 🧠 Visão Geral

AZ TV é uma plataforma de mídia indoor para gerenciamento de conteúdos digitais em players Android (TV Box, Smart TVs). O projeto é multi-tenant, baseado em Laravel, e conta com um painel administrativo para que cada cliente gerencie seus próprios conteúdos, dispositivos e configurações. O backend será responsável por controlar mídias, agendamentos, planos, comandos remotos (futuramente via WebSocket) e comunicação com o aplicativo player.

---

## 🎯 Objetivo

Permitir que clientes da plataforma:

- Cadastrem players e ativem via QR Code ou código
- Enviem vídeos, imagens e HTML para reprodução
- Criem playlists e definam agendamentos
- Apliquem configurações personalizadas por player
- Gerenciem planos e consumo de armazenamento
- Visualizem relatórios e estatísticas
- Tenham conteúdos automáticos (clima, frases, cotações)
- Recebam alertas e comandos em tempo real (futuramente)

---

## 🧱 Arquitetura

### Backend

- **Laravel 11+**
- Multi-tenant com `stancl/tenancy`
- API RESTful para comunicação com players
- Integração futura com Socket.io para WebSockets

### Frontend

- Inertia.js + React
- TailwindCSS + shadcn/ui
- Responsivo (suporte desktop/tablet)

### Storage

- Bucket S3-like (Backblaze B2 ou MinIO)
- Uploads com compressão via FFmpeg (em background)
- Thumbnails automáticos

---

## 🔐 Usuários e Perfis

| Perfil       | Acesso                                          |
| ------------ | ----------------------------------------------- |
| Admin Master | Gerencia clientes, planos, APKs, estatísticas   |
| Cliente      | Gerencia seus próprios players, mídias e config |

---

## 🔧 Funcionalidades por Módulo

### 1. **Autenticação e Multi-tenant**

- Login para admin e clientes
- Criação de tenants com dados iniciais
- Subdomínio ou slug por tenant
- Middleware de isolamento por tenant

---

### 2. **Painel Admin Master**

- Gerenciar contas de clientes (CRUD)
- Definir planos: número de players, armazenamento
- Ver estatísticas globais
- Upload de APKs personalizados
- Geração de links curtos e QR Code para APKs

---

### 3. **Painel do Cliente**

#### a) Dashboard

- Resumo de players online/offline
- Espaço usado vs. espaço total
- Últimos conteúdos exibidos
- Avisos importantes

#### b) Players

- Cadastro e ativação de players
- Visualizar status: online, versão do app, IP, localização, etc.
- Atribuição de apelido, local e grupo
- Configurações por player:
    - Volume
    - Tempo entre mídias
    - Looping
    - Senha de acesso (caso ativado)
    - Tema visual (fundo, cores)

#### c) Mídias

- Upload de arquivos (MP4, JPG, PNG, HTML, PDF)
- Organização em pastas ou tags
- Geração automática de thumbnails
- Compressão com FFmpeg (background job)
- Definição de tempo de exibição (por mídia)

#### d) Playlists

- Criar listas de reprodução
- Definir ordem e looping
- Atribuir playlists a players
- Suporte a agendamento:
    - Intervalo de datas
    - Dias da semana
    - Horários do dia
    - Prioridade

#### e) Conteúdos Automáticos

- Ativar/desativar por player
- Tipos:
    - Previsão do Tempo
    - Cotação de moedas
    - Frases motivacionais
    - Dicas de saúde
    - Vídeos engraçados (via YouTube ou domínio público)
    - Tabela de preços via Excel

#### f) Estatísticas (Futuro)

- Exibição local salva no player e sincronizada periodicamente
- Relatórios por conteúdo, por player, por período
- Gráficos de uso e exibição

---

### 4. **Provisionamento**

- Geração de QR Code ou token para ativação do player
- Após ativação, player recebe:
    - Configurações padrão
    - Playlist atribuída
    - Temas e preferências visuais

---

### 5. **Comandos Remotos (Futuro)**

- Envio de comandos via WebSocket:
    - Reiniciar player
    - Capturar screenshot
    - Atualizar playlist
    - Resetar configurações
- Comandos em tempo real com feedback do player

---

### 6. **Alertas e Notificações**

- Player offline por mais de X horas → envia e-mail
- Erros de reprodução → log no painel
- Uso próximo do limite de armazenamento

---

### 7. **Sistema de Planos**

- Plano Inicial: 50 players, 25 GB
- Plano Intermediário: 100 players, 50 GB
- Plano Avançado: 300 players, 150 GB
- Player extra: R$2~R$3 (varia por plano)
- Cada player adicional adiciona +500MB ao total
- Verificação de limites no painel + bloqueios futuros

---

## 🗃️ Banco de Dados (entidades principais)

- **users** (admin e clientes)
- **tenants**
- **players**
- **media_files**
- **playlists**
- **playlist_items**
- **player_logs**
- **apk_versions**
- **content_modules** (clima, frases, etc.)
- **plans**
- **subscriptions**
- **settings**

---

## 📅 Roadmap Sugerido

1. Autenticação + multi-tenant
2. Cadastro de clientes + painel admin
3. Upload de mídias + gestão de players
4. Sistema de playlists
5. Integração básica com player (API REST)
6. Provisionamento via token/QR
7. Agendamento de conteúdos
8. Integração com Socket.io (fase 2)
9. Conteúdos automáticos
10. Estatísticas + alertas

---

## ✅ Considerações Finais

A plataforma AZ TV será um serviço completo de mídia indoor com foco em automação, segurança e escalabilidade, sem dependência de soluções MDM pagas, e com diferencial competitivo no mercado B2B de sinalização digital.
