# Comic Management System

Este é um sistema de gerenciamento de quadrinhos desenvolvido com Laravel. Ele permite que usuários criem, editem, visualizem e organizem quadrinhos em coleções. O sistema também inclui uma área de administração para gerenciar usuários e realizar análises.

## Funcionalidades

### Geral
- Exibição de quadrinhos, coleções e perfis públicos
- Autenticação de usuários (login, registro e redefinição de senha)
- Perfis públicos e privados para cada usuário

### Gerenciamento de Quadrinhos
- Criação, edição e visualização de quadrinhos
- Suporte para organização e reordenação de páginas de quadrinhos
- Acesso a quadrinhos por ID ou slug
- Upload e gerenciamento de páginas dos quadrinhos

### Coleções de Quadrinhos
- Criação, edição e exibição de coleções
- Suporte para reordenação de quadrinhos dentro das coleções

### Administração
- Dashboard administrativo para gerenciar usuários
- Análise de tráfego e de referências
- Exclusão, edição e atualização de usuários

### Análises
- Coleta de dados de tráfego e visualizações de quadrinhos

## Rotas Web

### Usuários e Autenticação
- `/login` - Exibe o formulário de login e autentica o usuário.
- `/register` - Exibe o formulário de registro de novos usuários.
- `/logout` - Encerra a sessão do usuário.
- `/password/reset` - Envia o link para redefinir a senha.
- `/profile` - Exibe o perfil do usuário autenticado.
- `/profile/edit` - Permite ao usuário editar seu perfil.
- `/profile/id/{id}` - Exibe o perfil público de um usuário via ID.
- `/profile/{username}` - Exibe o perfil público de um usuário via nome de usuário.

### Quadrinhos
- `/comics` - Exibe a lista de todos os quadrinhos.
- `/comics/create` - Permite a criação de um novo quadrinho.
- `/comics/id/{id}` - Exibe um quadrinho específico por ID.
- `/comics/{slug}` - Exibe um quadrinho específico por slug.
- `/comics/{comic}/edit` - Permite editar um quadrinho existente.
- `/comics/{comic}/reorder-pages` - Reordena as páginas de um quadrinho.
- `/comics/{comic}/pages` - Adiciona novas páginas a um quadrinho.
- `/comics/{page}/deletePage` - Exclui uma página específica de um quadrinho.

### Coleções
- `/collections` - Exibe todas as coleções de quadrinhos.
- `/collections/create` - Permite a criação de uma nova coleção.
- `/collections/{collection}` - Exibe uma coleção específica.
- `/collections/{collection}/edit` - Permite editar uma coleção existente.
- `/collections/{collection}/sort/update` - Atualiza a ordem dos quadrinhos na coleção.

### Administração (apenas para usuários autenticados)
- `/admin` - Exibe a lista de usuários.
- `/admin/dashboard` - Exibe o dashboard de administração.
- `/admin/analytics` - Exibe a página de análises.
- `/admin/users` - Exibe e gerencia a lista de usuários.
- `/admin/users/{id}/edit` - Edita um usuário específico.
- `/admin/users/{id}` - Exclui um usuário específico.

## Rotas API

### Usuários
- `/user` - Retorna os dados do usuário autenticado.

### Quadrinhos
- `/comics` - Retorna todos os quadrinhos.
- `/comics/{id}` - Retorna um quadrinho específico por ID.
- `/comics/{id}/pages` - Retorna as páginas de um quadrinho por ID.

### Coleções
- `/collections` - Retorna todas as coleções de quadrinhos.
- `/collections/{id}` - Retorna uma coleção específica por ID.

### Análises
- `/analytics` - Armazena os dados de análise.

## Instalação

1. Clone o repositório:
    ```bash
    git clone https://github.com/cosmosgc/comic.git
    ```
2. Instale as dependências do Composer:
    ```bash
    composer install
    ```
3. Configure o arquivo `.env` com as suas informações de banco de dados.
5. Execute as migrações:
    ```bash
    php artisan migrate
    ```
6. Execute o laravel:
    ```bash
    php artisan serve
    ```

## Como contribuir

1. Faça um fork do projeto.
2. Crie sua feature branch (`git checkout -b feature/minha-feature`).
3. Faça commit das suas mudanças (`git commit -am 'Adiciona nova feature'`).
4. Envie para o branch (`git push origin feature/minha-feature`).
5. Crie um novo Pull Request.

