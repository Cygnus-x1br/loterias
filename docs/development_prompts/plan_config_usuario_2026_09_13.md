# Implementação do Painel de Configuração de Usuário

## Descrição do Objetivo
O objetivo é implementar a integração do painel de configuração de usuário (onde se pode alterar a senha e atualizar dados) e adicionar um menu dropdown no canto superior direito (topbar) para que o usuário logado possa acessar essas configurações e também a opção de sair (logout).

Atualmente, as rotas e componentes básicos (`resources/views/livewire/pages/auth/profile.blade.php` e os formulários internos) já foram gerados pelo Laravel Breeze, mas não estão integrados visualmente ao layout personalizado (`layouts/app.blade.php`) e não há um link de acesso no menu principal.

## User Review Required
Nenhuma decisão crítica que afete a lógica de negócios. A alteração será puramente de interface (UI) e integração de rotas já existentes.

## Proposed Changes

### Topbar (Menu Superior)
#### [MODIFY] `resources/views/layouts/partials/topbar.blade.php`
- Transformar a exibição do nome do usuário e o botão de logout num dropdown usando Alpine.js (`x-data="{ dropdownOpen: false }"`).
- Adicionar o link "Minha Conta" (ou "Configurações") no dropdown, apontando para a rota `route('profile')`.
- Manter o botão de "Sair" (logout) dentro desse mesmo dropdown.

### View de Perfil (Profile)
#### [MODIFY] `resources/views/livewire/pages/auth/profile.blade.php`
- Adaptar o layout para corresponder ao tema do painel (Lotofácil Analytics).
- Remover a tag `<x-slot name="header">` que não é suportada pelo `app.blade.php` personalizado e, em vez disso, injetar o `$title` correspondente (ex: `Configurações de Conta`).

### Documentação (Regra de Usuário)
#### [NEW] `docs/development_prompts/plan_config_usuario_2026_09_13.md`
- Salvar uma cópia deste plano no diretório especificado pela regra global.

## Verification Plan

### Manual Verification
- Clicar no nome de usuário no canto superior direito e verificar se o menu dropdown se abre corretamente.
- Clicar na opção "Minha Conta" e validar se a página de edição de perfil carrega sem erros de layout.
- Testar a alteração de senha (se possível, ou apenas garantir que o formulário está renderizando e funcional).
- Testar o clique no botão "Sair" dentro do menu para garantir que o logout continua funcionando.
