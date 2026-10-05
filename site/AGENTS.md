# Contexto do site institucional provisório

Esta pasta foi preparada para se tornar um repositório independente. Não use caminhos que apontem para `../assets/` ou outros arquivos da landing original.

O foco é apresentar a Veneza Piscinas como loja especializada com orientação técnica e central de conhecimento. A navegação começa pelas necessidades do visitante e segue para produtos, explicações, projetos e contato.

O conteúdo visível deve ficar em português do Brasil. Use apenas informações confirmadas na landing atual ou pela direção. Não invente depoimentos, preços, estoque, horários, prazos, capacidades de equipamentos, dosagens químicas ou recomendações automáticas de dimensionamento.

O projeto não tem framework nem build. As páginas HTML repetem cabeçalho e rodapé; ao editar a navegação ou os contatos, atualize todas as páginas. O CSS está em `assets/css/site.css`, e o JavaScript em `assets/js/site.js`.

A Central de Conhecimento está em `conhecimento.html` e `artigo.html`. A cópia local dos 152 artigos publicados está em `assets/data/posts-data.js`; não editar manualmente. `assets/js/config.js` define a URL futura da API; `assets/js/content.js` implementa consulta, fallback, ordenação e sanitização. `knowledge-list.js` e `knowledge-article.js` cuidam das telas. Não exponha registros `PE`/`PO` nem use `innerHTML` com HTML de artigo. Ao integrar o PENA, mantenha IDs estáveis, URLs de mídia válidas e contrato de API documentado em `README.md`.

O formulário de `contato.html` abre uma mensagem no WhatsApp. Ele não faz diagnóstico técnico. Mantenha rótulos, validação, navegação por teclado e suporte a movimento reduzido.

Antes de publicar em um novo repositório, siga o checklist de `README.md`. Esta versão é provisória e não deve substituir a landing page existente por acidente.
