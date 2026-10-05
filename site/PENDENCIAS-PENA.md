# Pendências para a integração com o PENA

Registro da revisão da Central de Conhecimento em 05/10/2026. São problemas conhecidos da versão provisória, não funcionalidades concluídas. O site institucional continua estático; a API do PENA está apenas preparada localmente, sem integração com a base real ou implantação.

## Prioridade alta — validar a retirada de publicações na integração real

O código foi ajustado para usar o snapshot somente enquanto `apiBaseUrl` estiver vazio. Após configurar a API, respostas `404`/`410` e falhas não ressuscitam artigos do snapshot. Falta validar esse comportamento com a API real e decidir a política editorial para períodos de indisponibilidade.

- Testar a distinção entre falha de rede/servidor e resposta editorial `404`, `410` ou estado não publicado com a API implantada.
- Definir com o PENA se o fallback será permitido após a API entrar em produção. Se for, estabelecer prazo de validade e mecanismo de invalidação para a cópia local.
- A API pública deve entregar apenas conteúdos publicados. Validar o estado também na leitura individual; não depender somente da interface para proteger rascunhos.
- Testar publicação, alteração, despublicação, exclusão, `404`, `410` e indisponibilidade temporária da API, inclusive com URL direta de artigo antigo.

## Prioridade média — corrigir o card de destaque

Em `assets/css/site.css`, `.content-card--featured { display: grid }` aparece antes de `.content-card { display: flex }`. Como as duas regras têm a mesma especificidade, a segunda vence e a imagem do destaque fica empilhada e grande em vez de ocupar metade do card.

- Corrigir a cascata/especificidade sem alterar o layout dos demais cards.
- Conferir o destaque em desktop, tablet e celular, com imagem válida, imagem ausente e imagem que falha ao carregar.

## Prioridade média — SEO das páginas individuais

`artigo.html?id=...` entrega HTML inicial com título e Open Graph genéricos. `knowledge-article.js` troca os metadados depois que o JavaScript roda, mas robôs e prévias sociais podem não executar esse código. Falta uma URL canônica definida.

- Quando domínio e arquitetura de publicação estiverem definidos, gerar HTML pré-renderizado ou páginas estáticas por artigo, com URL canônica, título, descrição, Open Graph e imagem próprios.
- Testar uma URL individual com JavaScript desativado e com um depurador de prévias sociais, sem inventar metadados ausentes no acervo.
- Preservar IDs ou estabelecer redirecionamentos para que os links compartilhados continuem válidos.

## Prioridade média — reduzir custo da busca e do fallback

O arquivo `assets/data/posts-data.js` tem cerca de 933 KB e é carregado nas duas páginas mesmo sem a API estar configurada. Em `knowledge-list.js`, cada tecla da busca converte novamente o HTML dos 152 artigos em texto.

- Criar o índice textual uma vez após receber a lista, sem reinterpretar todos os corpos a cada tecla.
- Após integrar a API, carregar apenas campos de listagem na página de busca e buscar o corpo do artigo sob demanda.
- Medir tamanho transferido e tempo de interação em rede móvel antes de remover o snapshot; garantir um estado de erro compreensível caso a API não responda.

## Preparação para o desenvolvimento do PENA

O contrato provisório da API está em `README.md`. Antes de implementar o backend, confirmar com a equipe: esquema e credenciais do banco existente, significado exato dos estados `PP`/`PO`/`PE`, regra de ordenação, URL definitiva da API, política de CORS, hospedagem das mídias e estratégia de backup físico. Não migrar tabelas nem criar usuários de produção sem cópia de segurança verificável e confirmação desses dados. O sistema administrativo, suas credenciais e a API ainda não fazem parte desta pasta.
