# Contexto do projeto — Veneza Piscinas

## Objetivo do site

Este repositório contém o site estático da Veneza Piscinas. A página principal é uma landing page comercial voltada a Recife e região metropolitana. Seu objetivo primário é transformar interesse em pedidos de orçamento pelo WhatsApp; apresentação institucional e educação do cliente são objetivos de apoio.

A oferta enfatizada atualmente inclui filtros e motobombas, aquecedores, geradores de cloro, geradores de ozônio, iluminação LED, projeto hidráulico e revestimento em manta armada. Confiança é construída com projetos realizados, depoimentos, clientes, marcas parceiras, FAQ e informações de contato.

O texto visível ao público deve permanecer em português do Brasil, com linguagem clara, técnica sem ser excessivamente especializada e orientada a benefícios. Não prometa modelos, estoque, prazo ou resultado técnico sem confirmação comercial.

## Estado atual da landing page

`index.html` segue este funil, nesta ordem:

1. Cabeçalho compacto com logo, menu de produtos e redes sociais.
2. Hero com proposta de valor, CTA de orçamento, carrossel de uma foto e quatro vídeos e quatro indicadores de confiança.
3. `#produtos`, com seis cards: filtros/motobombas, aquecedores, cloro, ozônio, iluminação e projeto hidráulico.
4. `#como-comprar`, com cinco passos do contato à instalação.
5. `#projetos`, com portfólio em foto/vídeo e visualizador em tela cheia.
6. `#depoimentos`, com avaliações e depoimentos em vídeo.
7. `#clientes` e `#parceiros`, com logos locais.
8. `#duvidas`, com 14 perguntas frequentes em acordeão.
9. CTA final, rodapé completo, botão de retorno ao topo e botão flutuante do WhatsApp.

Há dois diálogos nativos: `#gallery-viewer`, para ampliar projetos, e `#product-modal`, para detalhar produtos e serviços. A experiência inclui navegação por teclado, gestos no visualizador de projetos, pausa de mídia fora da tela e respeito a `prefers-reduced-motion`.

## Arquitetura e fontes de verdade

Não há framework, gerenciador de pacotes ou compilação. O site usa HTML, CSS e JavaScript puro e deve continuar funcional em hospedagem estática e em subdiretórios.

| Arquivo | Responsabilidade |
| --- | --- |
| `index.html` | Estrutura, textos, CTAs, FAQ, cards, projetos, depoimentos, clientes, parceiros e rodapé da landing page. |
| `assets/css/styles.css` | Todo o sistema visual e responsivo da landing page. |
| `assets/js/carousels.js` | Carrossel do hero e rotação automática dos cards de soluções. |
| `assets/js/catalog-faq.js` | Acordeão do FAQ e lógica legada de filtros de catálogo. A landing atual não possui o catálogo filtrável. |
| `assets/js/product-modal.js` | Dados e comportamento dos modais de produtos/serviços, mídias e mensagens de orçamento. |
| `assets/js/project-gallery.js` | Rotação, ampliação, zoom, teclado e gestos da galeria de projetos. |
| `assets/js/testimonials.js` | Controles customizados e coordenação dos vídeos de depoimentos. |
| `assets/js/site-ui.js` | Menu responsivo, rolagem, efeitos de entrada, botão de topo e comportamento do CTA flutuante. |
| `posts.html` | Página complementar de artigos; seus estilos estão embutidos no próprio HTML. |
| `assets/js/posts.js` | Busca, categorias, sanitização e leitor modal dos artigos. |
| `assets/data/posts-data.js` | Snapshot gerado dos artigos em `window.VENEZA_POSTS`; não editar manualmente se houver uma nova exportação. |
| `assets/images/` e `assets/videos/` | Mídias finais publicadas. |
| `assets/sources/` | Originais, referências e histórico de edição; não usar diretamente na interface sem intenção explícita. |

Leia também `README.md` antes de mudanças estruturais ou de conteúdo. Para mídias, consulte `assets/README.md` e os READMEs específicos nas pastas de produtos, clientes e parceiros.

## Contratos entre HTML, CSS e JavaScript

Preserve estes contratos ou atualize todos os consumidores na mesma alteração:

- Os valores de `data-modal-trigger` e `data-modal-link` devem existir como chaves de `productLines` em `assets/js/product-modal.js`.
- O modal de `projeto` monta sua lista a partir de `[data-modal-trigger="projeto"] .priority-card__slide`; cada slide precisa conter `img` e `figcaption`.
- Os demais produtos têm conteúdo visual tanto nos cards de `index.html` quanto em `productLines`. Ao trocar produto, marca, nome ou imagem, confira os dois lugares.
- Um carrossel de card usa `[data-card-carousel]`, filhos `.priority-card__slide` e um primeiro item `.is-active`.
- O carrossel do hero depende de `.hero-carousel`, `.hero-photo`, setas e pontos correspondentes. Mantenha a quantidade e os rótulos dos pontos sincronizados com os slides.
- Cada `.gallery-card` deve conter mídia (`img` ou `video`) e título em `figcaption b`. `project-gallery.js` cria o botão de ampliação dinamicamente.
- Vídeos de depoimento usam `.testimonial-video` dentro de `.testimonial-media`; os controles são gerados por JavaScript.
- Cada `.faq-question` usa `aria-controls` apontando para o `id` único da resposta, e `aria-expanded`/`hidden` representam o estado inicial.
- Não renomeie IDs internos dos diálogos sem revisar os scripts correspondentes. A classe `body.modal-open` bloqueia a rolagem enquanto um diálogo está aberto.
- IDs de seções são destinos do cabeçalho e do rodapé. Se um ID mudar, atualize todos os links de âncora.
- Todos os scripts da landing usam `defer` e são isolados em IIFEs. Evite globais e dependências de ordem quando não forem necessárias.

Ao adicionar interação a um elemento que não é nativamente interativo, prefira primeiro `button` ou `a`. Se a estrutura existente exigir outro elemento, preserve teclado, foco, `role`, `tabindex` e atributos ARIA. Ao fechar um modal, o foco deve retornar ao acionador.

## Identidade visual e responsividade

- Fonte principal: Space Grotesk via Google Fonts, com fallback Arial/sans-serif.
- Paleta e tokens principais: variáveis em `:root` no início de `assets/css/styles.css`.
- Direção visual: azuis/ciano, textura de água, superfícies translúcidas, cantos arredondados e CTAs de alto contraste.
- Conteúdo central: `.container` com máximo de 1180 px.
- Largura mínima suportada pelo `body`: 320 px.
- O CSS é monolítico e contém ajustes principais em 1000, 900, 720, 600 e 480 px, além de casos de baixa altura/orientação. Procure regras posteriores antes de concluir que um seletor não tem override.
- Preserve estilos de `:focus-visible` e blocos de `prefers-reduced-motion`.
- Evite estilos inline novos; há um caso legado no card de gerador de cloro, mas a regra geral é centralizar estilos em `assets/css/styles.css`.

## Fluxo de branches e commits

O histórico do projeto usa um Gitflow leve, com `main` representando o estado publicado e `develop` representando a próxima versão em integração. Preserve esse fluxo:

- Não faça commits diretos em `main` ou `develop`. Crie a branch de trabalho a partir de `develop` e mantenha cada branch focada em uma mudança.
- Use os prefixos observados no repositório: `feature/<assunto>` para funcionalidades ou conteúdo novo, `fix/<assunto>` para correções e `refactor/<assunto>` para reorganizações internas sem mudança intencional de comportamento. Para manutenção simples, `chore/<assunto>` é aceitável.
- Prefira nomes curtos, em minúsculas, separados por hífens. Exemplos: `feature/client-partner-showcase`, `fix/project-gallery-image-zoom` e `refactor/extract-static-assets`.
- Faça commits pequenos e atômicos, com mensagens curtas em inglês, no imperativo e com o prefixo correspondente: `feat:`, `fix:`, `refactor:` ou `chore:`. Exemplo: `feat: add verified Google reviews`.
- Antes de commitar, confira `git status`, `git diff` e `git diff --staged`. Não inclua arquivos modificados, removidos ou gerados por outra tarefa; mídia pesada e fontes só entram quando forem parte explícita da mudança.
- Valide a alteração antes do commit conforme a seção de execução e validação deste arquivo. Em JavaScript, rode `node --check` nos arquivos alterados quando Node estiver disponível; em conteúdo e mídia, confira caminhos relativos, textos alternativos, dimensões e referências.
- Ao concluir a branch, integre-a em `develop` por merge, preservando o histórico. O histórico existente usa mensagens como `merge: add <change> to develop`.
- Depois de validar o conjunto integrado em `develop`, faça o release para `main` por merge. Use uma mensagem objetiva como `merge: release <change>` e só publique aquilo que foi verificado.
- Não reescreva o histórico compartilhado com `push --force` e não use `reset --hard` ou `checkout` destrutivo para descartar alterações sem autorização explícita.

## Conversão e contatos

O telefone comercial atual é `+55 81 98298-3545`, usado como `5581982983545` nas URLs `wa.me`. Ele aparece em vários links de `index.html`, em `posts.html` e na função `whatsappUrl()` de `assets/js/product-modal.js`. Uma troca de número exige busca global.

Mensagens do WhatsApp são específicas ao contexto. Preserve a intenção do CTA ao editá-las e use `encodeURIComponent` quando a URL for construída no JavaScript. Links externos abertos em nova aba devem manter `rel="noopener"` ou `rel="noopener noreferrer"`.

Não remova CTAs principais sem avaliar o funil completo. Os pontos de conversão atuais estão no hero, nos modais, no CTA final, no botão flutuante, no rodapé e no leitor de artigos.

## Mídias

- Use caminhos relativos, nunca caminhos locais absolutos.
- Imagens finais pertencem a `assets/images/<categoria>/`; vídeos finais a `assets/videos/<categoria>/`.
- Preserve originais e referências em `assets/sources/`.
- As quatro imagens fotográficas preparadas do hero têm 1200 × 800 e podem ser recriadas no Windows por `scripts/prepare-hero-images.ps1`; posters extraídos dos vídeos são arquivos separados em `assets/images/hero/`.
- Os vídeos publicados do hero e da galeria de projetos são versões sem áudio. Originais locais com nomes terminados em `-original.mov` ou `-original.mp4` são ignorados pelo Git nas pastas previstas.
- Ao substituir uma mídia, atualize `alt`, `aria-label`, legenda, dimensões declaradas e poster do vídeo quando aplicável.
- Mantenha MP4 H.264 como opção amplamente compatível. No hero, ofereça WebM primeiro e MP4 como fallback; preserve o poster para carregamento e fallback visual.
- Comprima novas mídias e evite aumentar o peso inicial da página sem necessidade. Hero e conteúdo acima da dobra merecem atenção especial.

## Artigos

`posts.html` é uma página independente, com busca textual, filtro por assunto e leitura em `dialog`. O conteúdo vem de `assets/data/posts-data.js`, não de uma API em tempo real. URLs relativas herdadas são resolvidas contra `https://pena.venezapiscinas.com.br/`.

O snapshot público descrito no README contém 152 artigos `PP` da extração de 17/09/2026; os 32 registros `PO`/`PE` da base não pertencem ao artefato estático. O exportador e o conversor filtram `PP`, `posts.js` reforça essa regra e `tests/public-posts.test.cjs` bloqueia um snapshot inseguro no workflow de Pages. Para atualizar, siga o fluxo documentado no README. O histórico Git antigo pode continuar contendo a versão com registros não públicos; não reescreva histórico compartilhado sem autorização específica.

`posts.js` sanitiza o HTML importado com uma lista de elementos permitidos e bloqueados. Não substitua essa montagem segura por `innerHTML` direto. Preserve a validação de protocolos e a restrição de iframes incorporados ao YouTube.

## Como executar e validar

Não instale dependências para tarefas normais. Na raiz, sirva os arquivos com:

```powershell
python -m http.server 8000
```

Ou, no Windows com o launcher:

```powershell
py -m http.server 8000
```

Acesse `http://localhost:8000/`, `posts.html` e, quando relevante, `font-showcase.html`. Abrir o HTML diretamente serve para uma inspeção rápida, mas o servidor local representa melhor a hospedagem.

Há um teste automatizado de segurança dos snapshots; as interações da landing ainda exigem validação manual. Antes de concluir uma alteração:

1. Verifique `git diff` e preserve mudanças do usuário que não façam parte da tarefa.
2. Se JavaScript mudou e Node estiver disponível, execute `node --check` em cada arquivo alterado.
3. Teste a landing em desktop e em largura móvel, inclusive 320–480 px e ausência de rolagem horizontal inesperada.
4. Teste menu, todos os carrosséis, modais, FAQ, galeria, zoom/gestos, vídeos, retorno ao topo e WhatsApp.
5. Teste teclado, foco, fechamento com `Esc`, textos alternativos e redução de movimento.
6. Confira console e rede para erros, caminhos quebrados e mídia ausente.
7. Se artigos mudaram, teste busca sem acentos, categorias, hash `#artigo-<id>`, sanitização e fechamento do leitor.
8. Confirme que telefone, mensagem e produto do CTA correspondem ao contexto.

## Publicação e limitações conhecidas

`.github/workflows/deploy-pages.yml` testa se os snapshots de artigos contêm apenas `PP` e então publica os HTMLs da raiz, as pastas públicas `assets/{css,data,images,js,videos}`, os HTMLs de `site/` e `site/assets/{css,data,images,js}` no GitHub Pages. O job só publica commits da `main`, inclusive no acionamento manual. Não há build. `PENA/` e `assets/sources/` ficam fora dos novos artefatos; versões anteriores do Git ou de artefatos não são apagadas por isso.

O estado atual tem estas particularidades:

- `assets/css/styles.css` é grande e acumulativo; verifique cascata e overrides antes de adicionar regras.
- `posts.html` mantém CSS próprio embutido e não herda automaticamente mudanças visuais da landing.
- Produtos e contatos têm dados duplicados entre HTML e JavaScript.
- `catalog-faq.js` ainda contém lógica de filtro de catálogo sem marcação correspondente na landing.
- A página possui título e descrição básicos, mas não contém atualmente canonical, Open Graph, Twitter Cards ou dados estruturados.
- Fontes do Google, imagens remotas de artigos, WhatsApp e destinos sociais dependem de internet.

Trate essas limitações como contexto, não como autorização para refatoração ampla. Faça alterações focadas e preserve o comportamento existente salvo quando a tarefa pedir explicitamente uma mudança maior.

## Hospedagem disponível para o PENA

Atualização de 08/10/2026: o código Laravel, painel, API, testes e ferramentas do PENA foram movidos para `https://github.com/fesizw/PENA`, clone local em `C:\dev\PENA`. Este repositório mantém somente a landing, o site institucional e seus consumidores da API. Os parágrafos históricos abaixo descrevem a implantação planejada e podem citar o antigo prefixo `PENA/`; não recrie essa pasta nem trate seus caminhos como código atual. O workflow local de testes do site é `.github/workflows/site-tests.yml`, com imagens próprias em `site/tests/`. Desenvolvimento, migrações e release Laravel pertencem ao novo repositório.

O provedor ServHost disponibiliza cPanel para a conta da Veneza Piscinas. O painel mostra acesso SSH por chaves, com uma chave pública `id_rsa` já autorizada; a chave privada não deve ser versionada nem enviada ao chat. A conta possui gerenciador de arquivos, Git Version Control, backup/restauração, bancos MySQL e seletor de versões PHP.

O seletor do cPanel oferece PHP 8.3 e 8.4, mas o conjunto de dependências travado no PENA exige PHP >= 8.4.1. Os domínios existentes ainda aparecem em PHP 7.4, portanto não se deve alterar a versão herdada sem selecionar o domínio correto. O cPanel também mostra SSL ativo, Nginx caching ativo, diretório inicial `/home/veneza`, Application Manager e Setup Node.js/Python/Ruby. A disponibilidade desses recursos não substitui a confirmação de extensões PHP, Composer, document root e permissões no domínio/subdomínio.

Há espaço para criar subdomínios (o painel indica 4 de 10 em uso). Arquitetura planejada: domínio da landing estática, `pena.<domínio>` para o painel Laravel e `api.<domínio>` para a API/documentação OpenAPI. O GitHub Pages continua publicando somente a landing/site estáticos; o PENA deverá ser implantado separadamente no ServHost por SSH/cPanel.

Atualização de 05/10/2026, chamado ServHost #091143: o suporte informou que SSH externo é bloqueado na hospedagem compartilhada e ofereceu liberar o Terminal pelo cPanel. O usuário solicitou essa liberação e pretende pedir o bloqueio após concluir a implantação. A liberação ainda não foi confirmada. Terminal web não implica SSH/SFTP externo ou túnel MySQL disponível. A chave pública dedicada `veneza_servhost` foi autorizada no cPanel, mas o teste externo na porta 22 expirou antes da autenticação. Não repetir tentativas sem novas informações do provedor.

O `PENA/composer.lock` atual inclui dependências Symfony que exigem PHP >= 8.4.1. A oferta de PHP 8.3 no painel não basta para esse lock; confirmar PHP 8.4.1+ tanto na web quanto no Terminal antes de instalar.

Decisão posterior do usuário (05/10/2026): `venezapiscinas.com.br` receberá o site institucional de `site/`, não a landing da raiz. `/admin` no domínio principal deverá redirecionar para `pena.venezapiscinas.com.br`, com login no PENA; `api.venezapiscinas.com.br` deverá oferecer documentação interativa autenticada usando a mesma base de usuários. Artigos deverão ter URLs amigáveis; `POST_pena.LINK_POST` é candidato conhecido pela exportação, ainda sujeito a inspeção real de unicidade e conteúdo. Não confundir definição de arquitetura com funcionalidade já implementada.

O suporte confirmou a liberação do Terminal dentro do cPanel no chamado #091143; isso atualiza a pendência anterior, mas não comprova liberação de SSH externo. A captura de domínios mostra `api` em `/public_html/api.pena`, `pena` em `/public_html/pena.venezapiscinas` e domínio principal em `/public_html`, com redirecionamento para `www` a revisar. Preservar `loja` e arquivos de mídia legados; fazer backup dos arquivos e banco antes de substituir a publicação atual.

Backup do banco recebido em `C:\dev\veneza-backups\veneza_pena.sql.gz`, fora do repositório: integridade, SHA-256 e restauração isolada em MariaDB 10.11.19 verificados. Vistoria e limitações em `PENA/RELATORIO-BANCO.md`. O leitor do PENA passou com os 152 publicados e 32 não públicos; slugs públicos de `LINK_POST` válidos/únicos. Dez tabelas usam MyISAM/utf8mb3: revisar concorrência antes de liberar escrita. Isso não substitui backup dos arquivos/mídias nem conclui o desenvolvimento ou deploy. Nunca versionar o dump ou dados de autenticação legados.

Atualização editorial local (backlog 04): reordenação isolada usa mutex/revisão e auditoria InnoDB; leitura pública aplica HTML Purifier sem alterar conteúdo armazenado. O digest do conjunto `PP` invalida a ordem manual depois de publicação/despublicação legada. Isso **não** habilita CRUD sobre as tabelas MyISAM: nenhuma conversão foi aprovada/aplicada, e a validação utf8mb3 ainda deve ser integrada aos formulários futuros. Ver `PENA/SEGURANCA-EDITORIAL.md`; os testes concorrentes usam somente MariaDB sintético descartável.

Integração institucional local (backlog 06, ainda sem deploy): o PENA oferece `GET /api/public/posts/slug/{slug}` para `LINK_POST` publicado. O site em `site/` preserva a prévia estática, mas prepara páginas SSR em PHP para `/conhecimento/<slug>` com SEO no HTML inicial, estados HTTP reais e sem fallback para o snapshot quando a API estiver ativa. `site/.htaccess` prepara `/admin` como redirecionamento fixo ao PENA e o ID antigo como redirecionamento ao slug. GitHub Pages não executa PHP nem `.htaccess`. O canonical preparado é sem `www`; revisar antes de publicar o redirecionamento atual do cPanel para `www` para não criar loop. Não implantar em `/public_html` sem backup dos arquivos e preservação da loja/mídias legadas.

Backlog 07: roteiro em `PENA/HOMOLOGACAO-E-RELEASE.md` e empacotador de commit limpo em `scripts/prepare-pena-release.ps1`. A imagem de homologação local usa PHP 8.4. Preparação não equivale a instalação: faltam backup atualizado de banco/arquivos/mídias, decisão sobre escrita MyISAM, confirmação de PHP web e raízes seguras, preservação das URLs antigas de imagens no host `pena`, correção do conflito `www` e autorização de corte. Nunca publicar a landing da raiz como site institucional nem sobrescrever `public_html`/`loja` sem inventário e retorno ensaiado.
