# Contexto do site institucional provisório

Esta pasta foi preparada para se tornar um repositório independente. Não use caminhos que apontem para `../assets/` ou outros arquivos da landing original.

O foco é apresentar a Veneza Piscinas como loja especializada com orientação técnica e central de conhecimento. A navegação começa pelas necessidades do visitante e segue para produtos, explicações, projetos e contato.

O conteúdo visível deve ficar em português do Brasil. Use apenas informações confirmadas na landing atual ou pela direção. Não invente depoimentos, preços, estoque, horários, prazos, capacidades de equipamentos, dosagens químicas ou recomendações automáticas de dimensionamento.

O projeto não tem framework nem build. A prévia continua estática; a publicação de artigos no domínio definitivo usa PHP simples em `article.php`/`lib/knowledge.php` e regras Apache em `.htaccess`. As páginas HTML e a página PHP repetem cabeçalho e rodapé; ao editar navegação ou contatos, atualize todas. O CSS está em `assets/css/site.css`, e a interação comum em `assets/js/site.js`.

A Central de Conhecimento tem prévia em `conhecimento.html` e `artigo.html` e rotas definitivas `/conhecimento/` e `/conhecimento/<slug>`. A cópia local dos 152 artigos publicados em `assets/data/posts-data.js` não deve ser editada manualmente; ela só é carregada sob demanda fora do domínio definitivo. `assets/js/config.js` ativa a API no domínio HTTPS real, sem credenciais; em modo remoto não há fallback para o snapshot. `assets/js/content.js` implementa consulta, ordenação e sanitização; `knowledge-list.js` e `knowledge-article.js` cuidam da prévia. `article.php` consulta o slug na API pública e entrega SEO no HTML inicial com 404/410/503 reais. Preserve a consulta estrita a `PP`, o `no-store`, o escape de metadados, a sanitização do corpo, IDs/links antigos e URLs de mídia. Não use `innerHTML` com HTML não confiável.

`/admin` no domínio institucional só redireciona a destino fixo no PENA; não coloque autenticação nesse site. Em produção, `VENEZA_PUBLIC_API_ORIGIN` deve apontar para a origem HTTPS da API sem credenciais. O canonical atual é sem `www`, mas o cPanel ainda redireciona para `www`: rever a regra antes de publicar, sem criar loop. A camada PHP e `.htaccess` não funcionam no GitHub Pages; não afirmar que a URL amigável já está no ar. Os testes locais PHP/HTTP e Chromium usam API sintética isolada em `tests/`.

No pacote de produção, `assets/data/posts-data.js` é excluído. Em homologação HTTPS com hostname diferente do definitivo, `conhecimento/index.php` injeta no HTML a origem definida por `VENEZA_PUBLIC_API_ORIGIN`; `config.js` a usa para a listagem, sem snapshot. Se a variável não estiver válida, a rota PHP devolve 503. A prévia estática local continua usando o arquivo de dados sob demanda.

O formulário de `contato.html` abre uma mensagem no WhatsApp. Ele não faz diagnóstico técnico. Mantenha rótulos, validação, navegação por teclado e suporte a movimento reduzido.

Antes de publicar em um novo repositório, siga o checklist de `README.md`. Esta versão é provisória e não deve substituir a landing page existente por acidente.
