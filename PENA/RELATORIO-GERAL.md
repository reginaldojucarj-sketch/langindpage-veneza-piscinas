# PENA — relatório consolidado de implementação e correções

Situação técnica registrada em 07/10/2026: há código e testes locais para o PENA e sua integração com o site institucional, mas **nada foi instalado na ServHost**. Este relatório reúne o que foi construído, corrigido e verificado; “implementado localmente” não significa “habilitado na base real” nem “aprovado para produção”. O estado de commits e do GitHub Actions deve ser conferido no repositório após a publicação. Não houve migração na origem, criação de conta de produção ou corte.

## Funcionalidades disponíveis no código

| Área | Entrega local |
| --- | --- |
| Acesso e usuários (backlog 03) | Login em `/admin`, sessão, logout, papéis, cadastro/edição/desativação de usuários, troca/reset de senha, revogação de sessões e proteção do último administrador. |
| Autores e mídias (backlog 02) | Cadastro de autores, biblioteca de mídias, upload privado JPEG/PNG/WebP, derivados normalizados e seleção de autor/capa no editor. |
| Posts (backlog 01) | Listagem, busca e filtro; criação como rascunho, edição, prévia privada, publicação, ocultação e exclusão lógica; slug, categorias existentes, concorrência e auditoria. Escrita desativada por padrão. |
| Ordem e segurança editorial (backlog 04) | Ordem manual em tabelas auxiliares InnoDB com revisão, mutex, idempotência e auditoria; leitura pública somente de `PP`, HTML sanitizado e detecção de mudança do conjunto publicado. |
| API e documentação (backlog 05) | API administrativa `/api/admin/v1` e Swagger UI protegidos por contas da mesma tabela do painel; API pública de leitura paginada e detalhes de artigos publicados. As sessões de `pena` e `api` são separadas por host, sem SSO. |
| Site institucional (backlog 06) | Listagem integrada à API, páginas PHP `/conhecimento/<slug>` com SEO inicial e estados HTTP reais, redirecionamento de ID antigo e `/admin`; prévia estática separada. |
| Homologação (backlog 07) | Roteiro de instalação/retorno, empacotador que exige commit limpo, imagem PHP 8.4 e matriz de testes com MariaDB descartável. |

Detalhes e decisões estão em [README.md](README.md), [SEGURANCA-EDITORIAL.md](SEGURANCA-EDITORIAL.md), [API-ADMIN.md](API-ADMIN.md) e [HOMOLOGACAO-E-RELEASE.md](HOMOLOGACAO-E-RELEASE.md). O histórico cronológico das correções está em [RELATORIO-REVISAO-LOCAL.md](RELATORIO-REVISAO-LOCAL.md).

## O que foi feito, por componente

### Banco e fundação

- O dump privado recebido foi conferido por integridade e restaurado **somente em ambiente isolado**. A vistoria encontrou dez tabelas legadas MyISAM/utf8mb3 e 184 artigos: 152 `PP`, 26 `PO` e 6 `PE`. Os 152 slugs publicados são válidos e únicos; dois artigos publicados têm autor legado órfão e dois vínculos de categoria não públicos apontam para categoria ausente. O adaptador Laravel leu os 152 publicados e não expôs os outros 32. Inventário, limites dos campos e ressalvas em [RELATORIO-BANCO.md](RELATORIO-BANCO.md).
- A aplicação Laravel, o Docker local e cinco migrações **aditivas** foram preparados. As migrações criam as estruturas administrativas/editoriais InnoDB sem converter as dez tabelas antigas. Houve ensaio em cópia isolada do dump, preservando os 184 posts; nenhuma migração foi aplicada ao banco da hospedagem. O PHP 8.4 local foi alinhado ao requisito efetivo do `composer.lock`.
- As fixtures da suíte principal exigem `APP_ENV=testing`, SQLite `:memory:` e ausência de `DB_URL`; os ensaios MariaDB usam contêineres descartáveis sem rede. Esses mecanismos impedem que os testes apontem acidentalmente à base real.

### Painel, pessoas, autores e mídias

- Login, logout, CSRF, verificação de conta ativa, papéis `admin`/`editor`, revogação de sessões, gestão de usuários e troca/reset de senha foram implementados. A escrita de contas é serializada em InnoDB, com proteção do último administrador e auditoria; formulários antigos são recusados por versão. A conta inicial de produção **não** foi criada.
- Autores têm cadastro/edição e associação explícita à pessoa legada, sem importar senhas antigas nem reatribuir silenciosamente assinaturas históricas. A foto já vinculada permanece selecionável mesmo fora das 300 mídias mais recentes. A biblioteca aceita JPEG/PNG/WebP de até 2 MB, verifica conteúdo real e limites de dimensões/memória, conserva original privado e serve apenas derivado normalizado por `/media/{uuid}`. Desativação não apaga o original e a resposta pública de mídia usa `no-store`.
- O editor permite escolher autor e capa. A biblioteca comunica a seleção ao formulário aberto em outra aba sem perder texto não salvo; há fallback manual de URL quando a comunicação entre abas não está disponível. A atribuição persistente é feita pelo serviço editorial, com validação da mídia ativa.

### Edição, ordem e publicação

- `/admin/posts` oferece listagem paginada, busca/filtro, rascunho `PO`, edição, prévia privada, publicação `PP`, ocultação `PO` e exclusão lógica `PE`. O editor controla slug, categoria principal e vínculos existentes, autor, capa, conteúdo, impressão digital para conflitos e trilha de auditoria. Depois da primeira publicação, o slug fica estável até existir política de aliases. Não há cadastro/edição de categorias.
- Toda gravação editorial é bloqueada por padrão (`PENA_EDITORIAL_WRITES_ENABLED=false`). Mesmo com a flag ativada, o serviço recusa escrita se as tabelas envolvidas não forem InnoDB. Portanto o CRUD **não pode gravar com segurança na base real MyISAM atual**; nenhuma conversão foi feita ou autorizada.
- A ordem manual fica em tabelas auxiliares InnoDB, com mutex, revisão, digest do conjunto publicado, idempotência e auditoria. Se uma publicação legada alterar os `PP`, a ordem obsoleta é ignorada. A API verifica mudanças durante a leitura paginada e devolve `409` em conflito. HTML legado é sanitizado na saída, sem regravar o acervo; texto novo é validado contra os limites do utf8mb3.

### APIs, site institucional e preparação de entrega

- A API pública anônima retorna **somente `PP`**: resumos paginados e pesquisáveis sem HTML completo, detalhe por ID e detalhe por slug `LINK_POST`. A API administrativa versionada compartilha os serviços do painel e exige administrador, sessão e CSRF. Swagger UI e JSON OpenAPI são protegidos; em produção as rotas administrativas/documentação requerem `PENA_API_HOST` configurado. Cookies separados por host significam login distinto em `pena` e `api`, embora as contas sejam as mesmas.
- O site institucional ganhou listagem pela API e renderização PHP de `/conhecimento/<slug>` com título, descrição, canonical, Open Graph e JSON-LD no HTML inicial. Redireciona o ID antigo para o slug publicado e `/admin` para o PENA; responde com estados HTTP reais. A prévia estática usa snapshot local apenas quando não há API configurada. O GitHub Pages **não executa** PHP nem `.htaccess`.
- Foram preparados o workflow de testes, o roteiro de homologação/retorno e um empacotador que exige commit e árvore limpos. Nenhum pacote de produção foi gerado nesta validação; versionar o código e executar o workflow não instala o PENA. O workflow de Pages publica somente estáticos.

## Correções acumuladas nas revisões

| Tema | Achado e correção |
| --- | --- |
| Isolamento dos testes | Variáveis do Compose podiam prevalecer sobre a configuração do PHPUnit. O ambiente passou a fixar SQLite em memória e a suíte recusa banco/configuração inesperados antes das fixtures. |
| Entradas inválidas | E-mail enviado como array e ordenação enviada como mapa associativo deixaram de causar erro 500; agora são rejeitados por validação sem mutar dados. A busca SQL também trata `%`, `_` e `!` literalmente. |
| Senhas e concorrência de contas | Textos de senha parecidos com hash agora passam por `Hash::make()` explicitamente. Edição/reset verificam a versão recebida, invalidam sessões antigas e não sobrescrevem alterações concorrentes; o último administrador ativo continua protegido. |
| Ordem e acessibilidade | O foco deixa de voltar a um botão desabilitado após reordenação. Lista e revisão passaram a ser lidas do mesmo estado, impedindo que uma página obsoleta sobrescreva uma ordem mais nova; mutex, rollback, auditoria e digest cobrem o fluxo. |
| Leitura pública | Resposta de detalhe com ID divergente é rejeitada. Artigos não publicados foram retirados dos snapshots atuais, API e site não os exibem, e a falha da API ativa não reativa uma cópia local obsoleta. Sanitização e estados HTTP evitam expor HTML legado bruto ou disfarçar erro como publicação. |
| Upload e cache | A normalização de imagens passou a considerar o orçamento de memória do PHP antes de decodificar, a tratar orientação EXIF em JPEG e a recusar extensões/conteúdo incompatíveis. Mídia desativável passou a usar `Cache-Control: no-store` para novas respostas. |
| Integração e SEO | O artigo por ID/genérico deu lugar à rota PHP por slug com metadados iniciais, canonical e redirecionamento controlado. A listagem detecta alteração de snapshot entre páginas e evita misturar versões do acervo. |

O histórico técnico, inclusive resultados de cada fase e regressões introduzidas para esses casos, está em [RELATORIO-REVISAO-LOCAL.md](RELATORIO-REVISAO-LOCAL.md). As correções mais recentes são detalhadas abaixo.

### Revisão do conteúdo público e da interface

- O snapshot público da landing foi reduzido de 184 para **152 artigos `PP`** (1.263.055 para 933.325 bytes). Os 26 `PO` e 6 `PE` deixaram o arquivo atual. Exportador SQL, conversor PowerShell e leitor da página filtram `PP`; o workflow de Pages recusa snapshots com estados não públicos. O conversor lê JSONL explicitamente como UTF-8 para preservar acentos no Windows PowerShell 5.1.
- A busca pública e a administrativa tratam `%`, `_` e `!` como texto literal, com escape explícito em `LIKE`; os casos foram exercitados em SQLite e MariaDB sintético.
- O teste de navegador do PENA percorre login, Swagger, criação/edição/prévia/publicação/ocultação/exclusão lógica de post, visibilidade na API e logout. Seu executor agora funciona no Git Bash sem ajuste manual de caminhos e usa uma rede Docker interna.
- O relatório histórico foi corrigido para não apresentar como pendente a remoção local dos 32 registros não públicos. **Histórico Git e artefatos remotos antigos não são apagados por essa correção.**

### Continuação em 07/10/2026

- A listagem de artigos do painel recebeu paginação própria em português, responsiva e alinhada ao tema roxo. Os estilos genéricos de painel/formulário agora ficam em `public/admin.css`, também disponível nas telas de autores, mídia e posts. Um teste com mais de uma página verifica navegação e preservação dos filtros.
- Os formulários passaram a ter mensagens de validação e nomes de campos em pt-BR, com teste da resposta real do login. `config/app.php` adota `pt_BR` por padrão caso o ambiente não o defina.
- A busca de autores passou a interpretar `%`, `_` e `!` literalmente, inclusive em assinatura, slug e nome da pessoa. A rota e o serviço foram testados com dados sintéticos.
- Derivados de mídia desativável agora respondem com `Cache-Control: no-store`; cópias distribuídas **antes** desta correção podem persistir até expirar ou serem invalidadas no cache do host/navegador.
- O workflow de Pages só permite publicar a partir da `main`, inclusive em acionamento manual, e passa a montar o artefato apenas com pastas estáticas públicas: `assets/sources/` não entra em novos artefatos. A mudança não apaga publicações ou histórico anteriores.
- O teste Chromium do site agora cobre também a configuração real para uma origem de API HTTPS sintética, paginação, busca, URL amigável, falha da API e mudança de snapshot sem recorrer à cópia local. A origem HTTPS é interceptada no navegador; TLS, CORS e Apache reais continuam pendentes.
- A documentação da API foi alinhada ao OpenAPI: o endpoint público por slug está documentado. O tema roxo já aplicado ao painel e Swagger foi preservado; cores de erro, sucesso, aviso e métodos HTTP seguem distintas.

### Correção dos achados finais em 07/10/2026

- A publicação de um artigo legado `PO` agora confere novamente título, URL amigável, autor ativo, categoria principal vinculada, existência dos vínculos de categoria e conteúdo. Dados incompletos retornam erro de validação, sem trocar o estado para `PP`. O limite de `DESCRICAO_POST` foi explicitado em **65.535 bytes UTF-8** no formulário e no serviço, evitando que texto maior chegue ao banco como erro genérico.
- A migração inicial de usuários declara **InnoDB** explicitamente. O teste MariaDB também força o padrão da sessão para MyISAM antes dessa migração, para detectar regressão independentemente do padrão da hospedagem. Isso não converte nem libera a escrita das tabelas legadas.
- Os filtros da listagem administrativa alinham rótulos e campos no desktop e se empilham sem rolagem horizontal em 320 px. A tela Swagger recebeu um marco `<main>` acessível.
- A seleção de capa na biblioteca agora volta ao formulário aberto por uma comunicação entre abas da mesma origem: mídia gerenciada preenche `media_id`, e URL legada segura preenche `cover_url`. O rascunho não salvo é preservado; quando a comunicação não está disponível, permanece a cópia manual da URL. O teste Chromium cobre envio de imagem sintética e a escolha da capa entre abas.
- Os quatro testes JavaScript novos desse fluxo também entraram no job `site` do workflow de testes do PENA. Os resultados locais não substituem a conferência da execução no GitHub Actions após o push.
- Referências de documentação que ainda apresentavam recursos locais dos backlogs 01, 05 e 06 como ausentes foram atualizadas. Os bloqueios de produção continuam explícitos; essas correções não representam implantação.

## Onde conferir a implementação

| Responsabilidade | Arquivos principais |
| --- | --- |
| Rotas, acesso e contas | `routes/web.php`, `app/Services/AdminAccounts.php`, `app/Http/Middleware/EnsureActiveAdminSession.php`, `app/Providers/AppServiceProvider.php` |
| Autores e mídias | `app/Services/AuthorLibrary.php`, `app/Services/MediaLibrary.php`, `app/Services/ImageNormalizer.php`, `app/Http/Controllers/PublicMediaController.php` |
| Editor e ordem | `app/Services/PostEditor.php`, `app/Http/Requests/PostContentRequest.php`, `app/Services/EditorialOrdering.php`, `app/Repositories/LegacyPostRepository.php` |
| API e documentação | `routes/api.php`, `app/Http/Controllers/Api/AdminApiController.php`, `resources/openapi/admin-v1.php`, `resources/views/api/docs.blade.php` |
| Site institucional | `../site/article.php`, `../site/lib/knowledge.php`, `../site/conhecimento/index.php`, `../site/assets/js/content.js`, `../site/.htaccess` |
| Testes e entrega | `tests/Feature/`, `tests/Integration/`, `tests/Browser/`, `tests/js/`, `../.github/workflows/pena-tests.yml`, `../scripts/prepare-pena-release.ps1` |

## Verificações locais registradas em 07/10/2026

- PHPUnit em PHP 8.4.26, sem rede: **135 testes, 1.078 asserções**.
- Node: **35 testes** dos contratos do site, painel, seleção de mídia, Swagger e snapshots; os dois snapshots têm 152 IDs publicados idênticos.
- Conversor PowerShell com JSONL sintético: `PP` mantido, `PO`/`PE` rejeitados e UTF-8 preservado.
- Quatro suítes MariaDB 10.11 sintéticas, sem rede e descartáveis: acesso, ordenação/rollback, autores/mídias e posts. Incluem padrão MyISAM na sessão durante a migração InnoDB, limite real de 65.535 bytes em `TEXT`, busca literal, concorrência e recusa de escrita nas tabelas legadas MyISAM.
- Chromium com SQLite descartável: tema roxo, ciclo editorial completo, seleção de capa entre abas e Swagger aprovados. O site passou tanto no modo de prévia local quanto no modo de API HTTPS sintética; os testes HTTP/PHP aprovaram URLs, SEO, estados e sanitização.
- OpenAPI validado: **29 operações**. Pint aprovou **89 arquivos PHP**; Composer validou o manifesto e os requisitos de produção em PHP 8.4.26. A sintaxe dos JavaScripts alterados e `git diff --check` passaram. A montagem sintética do artefato Pages confirmou que `assets/sources/` ficou de fora em uma verificação anterior deste relatório.

As contagens acima são dos testes executados localmente; esses resultados não equivalem à execução do GitHub Actions nem à homologação no servidor real.

## Pendências antes de produção

1. Atualizar e ensaiar a restauração dos backups de banco **e** arquivos/mídias. Inventariar os document roots e preservar `loja`, mídias e URLs legadas antes de qualquer cópia ou alteração no `public_html`.
2. Resolver, com autorização específica e plano de retorno, a escrita nas tabelas legadas MyISAM e a coordenação com escritores antigos. Enquanto isso, `PENA_EDITORIAL_WRITES_ENABLED=false` e o bloqueio por engine devem permanecer. Não executar migrações na base real como consequência dos testes locais.
3. Confirmar PHP 8.4.1+ **na web** de `pena` e `api`, extensões, Composer, permissões, HTTPS, cookies, CSRF, CORS e cache Nginx; revisar o redirecionamento atual para `www` frente ao canonical sem `www`.
4. Homologar a API e o site nos subdomínios reais, inclusive imagens antigas, URL direta de artigos, 404/410/503, publicação/despublicação, documentação autenticada e criação segura do primeiro administrador.
5. Decidir o tratamento de categorias editáveis, autores órfãos e estado legado `PR`. O editor atual vincula categorias existentes, mas não as cadastra.
6. Decidir separadamente se é necessário remover cópias históricas de artigos não públicos do Git/Pages. Isso exigiria uma operação de retenção/publicação específica; não foi feita aqui.
7. Homologar na ServHost o novo `no-store` das mídias e invalidar cache antigo quando necessário. Verificar paginação e contraste do tema roxo em navegadores/dispositivos reais; testes sintéticos não substituem esse aceite.

Não incluir credenciais, dumps ou `.env` no Git. Não afirmar que o PENA está no ar até concluir os portões de [HOMOLOGACAO-E-RELEASE.md](HOMOLOGACAO-E-RELEASE.md).

## Atualização visual — tema roxo

O painel de login, administração e documentação Swagger usa agora uma paleta roxa com tokens em `public/admin.css` e overrides locais em `public/api-docs.css`. Botões, links, fundos, bordas, tabelas, paginação e a página de erro 503 acompanham o tema; vermelho, verde, âmbar e as cores dos métodos HTTP continuam distinguindo estados e operações. A landing e o site institucional não foram alterados por esse tema. As verificações de contraste do CTA branco/roxo e do botão secundário passaram; a suíte PHP completa e o E2E sintético em Chromium também passaram. Login e painel foram verificados em 375 px, sem rolagem horizontal ou ações cortadas. A alteração permanece apenas local, sem deploy.
