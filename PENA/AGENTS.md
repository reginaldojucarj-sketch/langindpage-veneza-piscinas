# Contexto de desenvolvimento — PENA

PENA é a aplicação Laravel 13 para o acervo de artigos da Veneza Piscinas. A landing page está na raiz do repositório e o site institucional provisório em `site/`. Leia `README.md` desta pasta, o `../AGENTS.md` e o `../site/README.md` antes de mudanças de integração.

O painel deve permanecer em `/admin`. Rotas de login e logout usam sessão/CSRF; nenhuma mutação administrativa pode ocorrer fora de autenticação e autorização. A API pública deve retornar apenas posts publicados e nunca expor rascunhos. Preserve a compatibilidade do contrato consumido por `site/assets/js/content.js`.

As tabelas legadas conhecidas aparecem em `../scripts/export-posts.sql`. O adaptador em `app/Repositories/LegacyPostRepository.php` é somente leitura e ainda não foi validado contra a base real. As migrações propostas criam apenas `pena_admin_users` e `pena_post_order`; nenhuma foi executada no banco real. **Não execute migrações, seeds, comandos de escrita nem crie contas na base existente antes de um dump físico verificável, testado por restauração.** A conexão MySQL remota conhecida não oferece TLS; não exporte dados nem envie novas credenciais por ela. Use acesso seguro provido pela hospedagem ou um túnel SSH. Nunca versione credenciais ou dumps.

O Docker local executa a aplicação sem alterar PHP/Composer globais. `PENA/.env` é local e ignorado. Testes usam SQLite em memória; não provam compatibilidade do esquema real. O GitHub Pages da raiz publica apenas arquivos estáticos e exclui esta aplicação PHP. Não configure `site/assets/js/config.js` para a API antes de implantá-la em HTTPS e testar as regras editoriais.

A hospedagem alvo é ServHost com cPanel. O painel informa SSH por chave pública autorizada, SSL ativo, Nginx caching, Git Version Control, Application Manager, bancos MySQL e PHP 8.3/8.4 disponíveis no seletor. Domínios existentes podem estar herdando PHP 7.4; confirmar e ajustar somente o subdomínio do PENA. Não armazenar a chave privada SSH, senha do cPanel ou credenciais do banco no repositório, nos `AGENTS.md` ou no chat.

Planejamento de subdomínios: domínio da landing; `pena.<domínio>` para o painel Laravel; `api.<domínio>` para a API e documentação OpenAPI protegida. Antes do deploy, confirmar document root em `PENA/public`, extensões `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml` e `ctype`, Composer/SSH, banco MySQL, HTTPS e regras de CORS. O GitHub Actions da raiz não hospeda o PENA.

Siga o Gitflow e as validações em `../AGENTS.md`. Mantenha mudanças focadas; não publique esta branch como produto concluído enquanto o backup, o CRUD e a conta inicial estiverem pendentes.

Em 05/10/2026, o suporte ServHost (#091143) confirmou bloqueio do SSH externo em hospedagem compartilhada e ofereceu Terminal pelo cPanel. O usuário solicitou liberação temporária; ainda aguardamos confirmação. Isso não habilita automaticamente SFTP ou túnel SSH. Exportação segura poderá ser feita pelo painel HTTPS. O lock atual exige PHP >= 8.4.1, apesar do requisito mínimo mais amplo declarado pelo framework.

Revisão local e pendências: `RELATORIO-REVISAO-LOCAL.md`. A suíte PHP exige SQLite `:memory:` e recusa outra configuração antes das fixtures; preservar essa trava e as variáveis `env`/`server` no PHPUnit (o Compose pode sobrepor apenas `env`). Testes JavaScript adicionais estão em `tests/js/`; o workflow `../.github/workflows/pena-tests.yml` testa sem publicar e sem credenciais. Não confundir os testes sintéticos com validação do banco real ou testes completos em navegador.

Atualização posterior: Terminal web liberado pelo suporte no chamado #091143; SSH externo continua não confirmado. O usuário definiu domínio principal para o site institucional, redirecionamento de `/admin` para `pena.venezapiscinas.com.br` e documentação interativa com login em `api.venezapiscinas.com.br`, usando os mesmos usuários do PENA. Esses requisitos ainda precisam ser implementados. Inspecionar `POST_pena.LINK_POST` para URLs amigáveis antes de escolher roteamento/migrar slugs. Não sobrescrever mídia legada, `loja`, banco ou site atual sem backup verificado e plano de retorno.
