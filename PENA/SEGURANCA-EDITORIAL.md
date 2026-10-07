# Segurança editorial e escrita concorrente

## Decisão nesta etapa

O esquema restaurado tem as tabelas legadas de posts, categorias e relações em MyISAM/utf8mb3. Não se usa `DB::transaction()` nem `lockForUpdate()` como garantia sobre essas tabelas. Nenhuma migração deste backlog foi executada no banco restaurado ou na hospedagem.

Alternativas avaliadas: (1) converter as tabelas alvo para InnoDB em uma cópia primeiro, revisar índices/charset e compatibilidade dos escritores, fazer backup físico e programar janela de manutenção/retorno; é o destino preferível para CRUD transacional, mas não foi autorizado nem executado. (2) manter MyISAM com locks de aplicação só seria consistente se o PENA e todos os escritores antigos obedecessem ao mesmo protocolo; isso ainda não foi confirmado, logo não serve para CRUD. (3) manter apenas a ordem em tabelas auxiliares InnoDB, sem prometer atomicidade cruzada; é a opção implementada para permitir a reordenação sem gravar no legado. A vistoria de engines, índices e charset está em `RELATORIO-BANCO.md`.

Na base real, a única escrita editorial autorizável nesta etapa continua sendo a ordem, mantida em tabelas auxiliares InnoDB. O backlog 01 acrescentou um editor de posts, mas ele nasce desativado e recusa escrita se as tabelas de origem e auxiliares não estiverem em InnoDB. Uma linha singleton em `pena_editorial_state` funciona como mutex transacional e guarda uma revisão inteira crescente e o SHA-256 do conjunto de IDs `PP`. O formulário de ordem envia revisão esperada e UUID de idempotência; a operação confere a revisão e o conjunto atual, substitui a ordem, incrementa a revisão e grava auditoria dentro da mesma transação InnoDB. Duas gravações iniciadas na mesma revisão não sobrescrevem uma à outra: uma conclui e a outra recebe conflito `409`. Falhas em qualquer etapa revertem as três alterações auxiliares; falha/conflito é auditado após o rollback quando a tabela de auditoria está acessível.

A repetição com a mesma chave, ator e payload retorna a revisão originalmente confirmada sem inserir outra ordem ou auditoria de sucesso. Reutilizar a chave para outro ator/payload é conflito. Não há campo de senha, cookie, HTML ou corpo completo na trilha. O resumo registra contagem/digest, ator, ação, entidade, revisão, resultado e correlação.

O leitor só aplica a ordem quando digest, IDs e posições são completos e correspondem ao conjunto `PP` observado. Se um escritor antigo publicar/despublicar durante ou depois da ordenação, a ordem fica obsoleta e o leitor retorna ao desempate por data/ID; nenhum post é alterado. Esse mecanismo detecta deriva de conjunto, mas MyISAM não oferece snapshot/locks transacionais: uma consulta pode refletir a leitura imediatamente anterior a uma publicação concorrente. CRUD de post/categoria permanece bloqueado até plano aprovado de conversão/coordenação, nunca se deve prometer atomicidade entre legado MyISAM e auxiliares InnoDB.

## HTML e charset

`HtmlSanitizer` usa [HTML Purifier 4.19.1](https://packagist.org/packages/ezyang/htmlpurifier) ([repositório upstream](https://github.com/ezyang/htmlpurifier)): allowlist de tags/atributos, apenas esquemas HTTP(S), sem estilos ou handlers e iframe restrito a embeds HTTPS do YouTube com ID de 11 caracteres. A listagem paginada retorna apenas metadados/resumo; o servidor sanitiza o corpo somente na resposta de detalhe. Não regrava nem destrói o HTML histórico. Título, slug e outros textos não são HTML confiável e devem ser escapados normalmente no template/cliente.

`LegacyUtf8mb3` rejeita UTF-8 inválido e pontos de código fora do BMP, apresentando erro por campo; também mede limites em caracteres Unicode, não bytes. O editor de posts aplica esta validação antes de gravar, inclusive no HTML. Não remover emojis ou truncar valores silenciosamente. Conversão para utf8mb4 exige ensaio em cópia, análise dos índices únicos e plano de retorno.

Na API pública, a listagem é paginada e exclui `html`; a busca `q` pode pesquisar o corpo no servidor, mas devolve somente os resumos correspondentes. O detalhe sanitizado permanece em `/api/public/posts/{id}`. O `meta.snapshot` combina conjunto publicado, revisão e ordem efetiva; na busca inclui também os IDs ordenados que correspondem ao termo. O servidor compara o estado antes/depois da leitura e responde `409` quando detecta deriva concorrente; o cliente recusa concatenar páginas de snapshots diferentes. Como MyISAM não oferece snapshot transacional, essa validação otimista detecta alterações entre leituras, mas não cria atomicidade real nem impede uma alteração imediatamente após a última verificação.

## Falhas e atomicidade

A tela administrativa captura a lista ordenada e sua revisão pela mesma leitura de estado em `publishedWithRevision()`. Se outra pessoa salvar enquanto a página está sendo montada, o formulário mantém a revisão que acompanhou a lista exibida; ao enviar, a revisão obsoleta gera `409` em vez de substituir silenciosamente uma ordem mais nova.

| Etapa | Persistência | Falha |
| --- | --- | --- |
| Validar versão e conjunto `PP` | Leitura do legado; não bloqueia o escritor antigo | `409` se versão/conjunto divergirem; não grava ordem |
| Limpar/inserir `pena_post_order` | InnoDB | rollback da transação |
| Atualizar digest/revisão | InnoDB | rollback da mesma transação |
| Gravar auditoria de sucesso | InnoDB, mesma transação | rollback de ordem e revisão |
| Auditar conflito/falha | Nova escrita InnoDB após rollback | se indisponível, log só com correlação/tipo de exceção; não registrar SQL/segredos |
| Alterar/criar post e relações | Implementado, porém **desativado na base real**; só roda se flag explícita e todas as tabelas envolvidas forem InnoDB | rollback de post, categorias, vínculos, revisão e auditoria na mesma transação; MyISAM retorna `503` antes de escrever |

Laravel repete a transação em deadlock até três tentativas. A operação de ordem é substitutiva, possui chave idempotente e não cria posts. O ID/ordem auxiliares não possuem FK para o legado, evitando impor mudanças às tabelas históricas.

O CRUD preparado no backlog 01 valida e sanitiza o post, confere um fingerprint do registro, relações e revisão global, grava categorias/vínculos, atualiza revisão/digest e registra auditoria de sucesso em uma transação. Conflito de edição retorna `409`, erro de campo `422` e engine/base indisponível `503`; reuso de slug retorna validação. O fingerprint detecta mudanças observadas, mas **não coordena um escritor legado externo**. `PENA_EDITORIAL_WRITES_ENABLED` permanece `false` até conversão e coordenação aprovadas. Quando o conjunto publicado muda, a ordem manual é invalidada para evitar ranking incompleto.

## Migrações adicionadas, ainda não instaladas

- `2026_10_05_000003_create_editorial_order_state_and_audit.php` cria estado e trilha, ambas InnoDB, e insere a revisão inicial `1` sem consultar nem alterar posts.
- `2026_10_05_000001_create_pena_post_order_table.php` declara a ordem como InnoDB.
- `pena_admin_users`, `pena_admin_access_lock`, `pena_admin_audit` e as tabelas do acervo permanecem fora do escopo destrutivo desta migração.

Antes de instalar em qualquer ambiente persistente, confirmar nomes/ausência das auxiliares, backup atualizado e restauração verificada, collation/engine efetivas, versão e permissões do MariaDB. Não aplicar em produção automaticamente. O rollback permanece deliberadamente manual, preservando dados de auditoria/ordem.

## Cobertura executável e limites

- PHPUnit/SQLite: validação de entrada, sanitização XSS, fluxo HTTP, conflitos, idempotência e injeção de falhas. SQLite não comprova mutex MySQL.
- `tests/Integration/editorial-order-mariadb.php`: esquema sintético e volume/socket dedicado; testa engines MyISAM/InnoDB, dez disputas de lock com duas conexões independentes, rollback em seis pontos, publicação MyISAM concorrente, idempotência e auditoria. O caminho de retry é exercitado com uma exceção `QueryException` de deadlock simulada; o teste não afirma ter provocado deadlock físico no servidor. É executado pelo workflow isolado; nunca apontar ao backup real.
- Falta: cadastro/edição de categorias, conversão planejada e aprovada do legado ou protocolo que suspenda escritores externos, revisão humana do HTML antigo e teste de navegador completo. Posts, autores e mídias têm interfaces locais; a escrita de posts segue bloqueada na base real.
