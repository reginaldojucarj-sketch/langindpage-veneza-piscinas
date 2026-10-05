# Contexto de desenvolvimento — PENA

PENA é a aplicação Laravel 13 para o acervo de artigos da Veneza Piscinas. A landing page está na raiz do repositório e o site institucional provisório em `site/`. Leia `README.md` desta pasta, o `../AGENTS.md` e o `../site/README.md` antes de mudanças de integração.

O painel deve permanecer em `/admin`. Rotas de login e logout usam sessão/CSRF; nenhuma mutação administrativa pode ocorrer fora de autenticação e autorização. A API pública deve retornar apenas posts publicados e nunca expor rascunhos. Preserve a compatibilidade do contrato consumido por `site/assets/js/content.js`.

As tabelas legadas conhecidas aparecem em `../scripts/export-posts.sql`. O adaptador em `app/Repositories/LegacyPostRepository.php` é somente leitura e ainda não foi validado contra a base real. As migrações propostas criam apenas `pena_admin_users` e `pena_post_order`; nenhuma foi executada no banco real. **Não execute migrações, seeds, comandos de escrita nem crie contas na base existente antes de um dump físico verificável, testado por restauração.** A conexão MySQL remota conhecida não oferece TLS; não exporte dados nem envie novas credenciais por ela. Use acesso seguro provido pela hospedagem ou um túnel SSH. Nunca versione credenciais ou dumps.

O Docker local executa a aplicação sem alterar PHP/Composer globais. `PENA/.env` é local e ignorado. Testes usam SQLite em memória; não provam compatibilidade do esquema real. O GitHub Pages da raiz publica apenas arquivos estáticos e exclui esta aplicação PHP. Não configure `site/assets/js/config.js` para a API antes de implantá-la em HTTPS e testar as regras editoriais.

A hospedagem alvo é ServHost com cPanel. O painel informa SSH por chave pública autorizada, SSL ativo, Nginx caching, Git Version Control, Application Manager, bancos MySQL e PHP 8.3/8.4 disponíveis no seletor. Domínios existentes podem estar herdando PHP 7.4; confirmar e ajustar somente o subdomínio do PENA. Não armazenar a chave privada SSH, senha do cPanel ou credenciais do banco no repositório, nos `AGENTS.md` ou no chat.

Planejamento de subdomínios: domínio da landing; `pena.<domínio>` para o painel Laravel; `api.<domínio>` para a API e documentação OpenAPI protegida. Antes do deploy, confirmar document root em `PENA/public`, extensões `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml` e `ctype`, Composer/SSH, banco MySQL, HTTPS e regras de CORS. O GitHub Actions da raiz não hospeda o PENA.

Siga o Gitflow e as validações em `../AGENTS.md`. Mantenha mudanças focadas; não publique esta branch como produto concluído enquanto o backup, o CRUD e a conta inicial estiverem pendentes.
