SELECT JSON_OBJECT(
  'id', CAST(p.ID_POST AS UNSIGNED),
  'title', p.TITULO_POST,
  'html', p.CONTEUDO_POST,
  'description', p.DESCRICAO_POST,
  'snippet', p.SNIPPET_POST,
  'status', p.STATUS_POST,
  'highlight', p.DESTAQUE_POST,
  'published_at', p.DATA_POSTAGEM_POST,
  'created_at', p.DATA_CRIACAO_POST,
  'updated_at', p.DATA_ULTIMA_MODIFICACAO_POST,
  'slug', p.LINK_POST,
  'keywords', p.KEYWORDS_POST,
  'author', COALESCE(NULLIF(a.ASSINATURA_AUTOR, ''), NULLIF(CONCAT_WS(' ', person.NOME_PESSOA, person.SOBRENOME_PESSOA), '')),
  'category', category.NOME_CATEGORIA,
  'categories', category_links.category_names,
  'image', COALESCE(NULLIF(p.URL_IMAGEM_POST, ''), images.ENDERECO_IMAGENS),
  'image_name', images.NOME_IMAGENS
) AS article
FROM POST_pena p
LEFT JOIN AUTOR_pena a ON a.ID_AUTOR = p.ID_AUTOR
LEFT JOIN PESSOA_pena person ON person.ID_PESSOA = p.ID_PESSOA
LEFT JOIN IMAGENS_pena images ON images.ID_IMAGENS = p.ID_IMAGENS
LEFT JOIN CATEGORIA_pena category ON category.ID_CATEGORIA = p.ID_CATEGORIA
LEFT JOIN (
  SELECT cp.ID_POST,
    GROUP_CONCAT(DISTINCT c.NOME_CATEGORIA ORDER BY c.NOME_CATEGORIA SEPARATOR ', ') AS category_names
  FROM CATEGORIA_POST_pena cp
  LEFT JOIN CATEGORIA_pena c ON c.ID_CATEGORIA = cp.ID_CATEGORIA
  GROUP BY cp.ID_POST
) category_links ON category_links.ID_POST = p.ID_POST
ORDER BY COALESCE(p.DATA_POSTAGEM_POST, p.DATA_CRIACAO_POST) DESC, p.ID_POST DESC;
