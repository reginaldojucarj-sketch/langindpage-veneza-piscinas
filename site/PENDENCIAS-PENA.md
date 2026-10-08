# Pendências para a integração com o PENA

Registro da revisão da Central de Conhecimento em 05/10/2026. As seções abaixo preservam os achados históricos da versão provisória. Os itens marcados como resolvidos foram tratados **apenas no código local**; não houve implantação nem aceite na hospedagem.

## Atualização local — backlog 06 (06/10/2026)

- Rota PHP `/conhecimento/<slug>` e consulta pública por `LINK_POST` implementadas localmente, com `PP` estrito, 404/410/503 reais, HTML inicial com canonical/Open Graph/JSON-LD e corpo sanitizado. O ID antigo redireciona para o slug; após publicação, a edição do slug é bloqueada até existir histórico de aliases.
- O snapshot de 152 artigos permanece apenas na prévia estática e é carregado sob demanda. No host institucional HTTPS a API fica ativa; falha ou retirada não reativa a cópia. Busca local indexada uma vez; card de destaque corrigido e sem duplicação. Testes de PHP/HTTP e Chromium sintéticos cobrem esses fluxos, mas não substituem a API/Apache reais.
- Pendências: definir/remover o redirecionamento atual para `www` antes de usar canonical sem `www`, confirmar PHP web/cURL/DOM/mbstring e mod_rewrite na ServHost, configurar `VENEZA_PUBLIC_API_ORIGIN` e CORS, revisar cache Nginx, mídias legadas, direitos editoriais e ensaiar publicação/despublicação com a API real. Fazer backup de arquivos antes de substituir `/public_html`; loja e landing não entram neste deploy.

## Prioridade alta — validar a retirada de publicações na integração real

O código foi ajustado para usar o snapshot somente enquanto `apiBaseUrl` estiver vazio. Após configurar a API, respostas `404`/`410` e falhas não ressuscitam artigos do snapshot. Falta validar esse comportamento com a API real e decidir a política editorial para períodos de indisponibilidade.

- Testar a distinção entre falha de rede/servidor e resposta editorial `404`, `410` ou estado não publicado com a API implantada.
- Definir com o PENA se o fallback será permitido após a API entrar em produção. Se for, estabelecer prazo de validade e mecanismo de invalidação para a cópia local.
- A API pública deve entregar apenas conteúdos publicados. Validar o estado também na leitura individual; não depender somente da interface para proteger rascunhos.
- Testar publicação, alteração, despublicação, exclusão, `404`, `410` e indisponibilidade temporária da API, inclusive com URL direta de artigo antigo.

## Resolvido localmente — card de destaque

A cascata do card foi corrigida no CSS local e o destaque passou nos testes sintéticos. Os itens seguintes registram a motivação original; ainda cabe conferir o resultado no domínio definitivo.

Em `assets/css/site.css`, `.content-card--featured { display: grid }` aparece antes de `.content-card { display: flex }`. Como as duas regras têm a mesma especificidade, a segunda vence e a imagem do destaque fica empilhada e grande em vez de ocupar metade do card.

- Corrigir a cascata/especificidade sem alterar o layout dos demais cards.
- Conferir o destaque em desktop, tablet e celular, com imagem válida, imagem ausente e imagem que falha ao carregar.

## Resolvido localmente — SEO das páginas individuais

O SSR PHP em `/conhecimento/<slug>` agora devolve título, descrição, canonical, Open Graph e dados estruturados no HTML inicial. Os itens seguintes são o histórico da pendência; prévias sociais e redirecionamentos ainda precisam de homologação no domínio definitivo.

`artigo.html?id=...` entrega HTML inicial com título e Open Graph genéricos. `knowledge-article.js` troca os metadados depois que o JavaScript roda, mas robôs e prévias sociais podem não executar esse código. Falta uma URL canônica definida.

- Quando domínio e arquitetura de publicação estiverem definidos, gerar HTML pré-renderizado ou páginas estáticas por artigo, com URL canônica, título, descrição, Open Graph e imagem próprios.
- Testar uma URL individual com JavaScript desativado e com um depurador de prévias sociais, sem inventar metadados ausentes no acervo.
- Preservar IDs ou estabelecer redirecionamentos para que os links compartilhados continuem válidos.

## Parcialmente resolvido — busca e snapshot da prévia

A busca local passou a preparar seu índice uma vez, e a API remota fornece resumos paginados sem HTML integral. O snapshot de aproximadamente 933 KB permanece na prévia estática, carregado sob demanda; medir desempenho em rede móvel e decidir sua retenção continuam pendentes. Os itens abaixo são o registro original.

O arquivo `assets/data/posts-data.js` tem cerca de 933 KB e é carregado nas duas páginas mesmo sem a API estar configurada. Em `knowledge-list.js`, cada tecla da busca converte novamente o HTML dos 152 artigos em texto.

- Criar o índice textual uma vez após receber a lista, sem reinterpretar todos os corpos a cada tecla.
- Após integrar a API, carregar apenas campos de listagem na página de busca e buscar o corpo do artigo sob demanda.
- Medir tamanho transferido e tempo de interação em rede móvel antes de remover o snapshot; garantir um estado de erro compreensível caso a API não responda.

## Preparação para a implantação do PENA

O contrato da API está em `README.md`, e o backend PENA está no [repositório independente](https://github.com/fesizw/PENA), com painel e API na mesma aplicação. Antes de implantá-lo, confirmar com a equipe a política editorial, URL definitiva da API, CORS, hospedagem das mídias, backup atualizado e tratamento das tabelas MyISAM. Não migrar tabelas nem criar usuários de produção sem cópia de segurança verificável e homologação. O sistema administrativo e a API ainda não fazem parte da publicação do site.
