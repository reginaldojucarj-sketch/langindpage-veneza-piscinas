# Vistoria do backup real — 05/10/2026

## Backup e isolamento

- Arquivo fornecido pelo usuário: `C:\dev\veneza-backups\veneza_pena.sql.gz`, fora do repositório.
- Tamanho: **212.326 bytes**.
- SHA-256: `6b747e1ee05d1800948fac18e976fa79b233e133f411c882916e69936e13ede1`.
- `gzip -t` aprovado; marcador de conclusão do dump: `2026-10-05 17:52:30`.
- O cabeçalho identifica origem MariaDB **10.11.19-MariaDB-cll-lve** e base `veneza_pena`.
- Restaurado sem erros em MariaDB **10.11.19**, base local `pena_audit`, contêiner `pena-restore-audit-20261005`, sem rede, sem portas publicadas e com `--skip-networking`. Dump montado somente para leitura. Comunicação com o verificador Laravel exclusivamente por socket Unix em volume local.
- Dados temporários da restauração em `tmpfs`: não constituem segundo backup persistente. O arquivo original não foi alterado.
- `mariadb-check --check pena_audit`: **10 tabelas OK**.
- Não houve conexão com a hospedagem, migrações, criação de usuários nem correção de registros reais nesta vistoria.

O dump não contém triggers, eventos ou rotinas. A restauração comprova que esse arquivo é utilizável, mas não prova que a exportação incluiu objetos que eventualmente existam no servidor e tenham sido omitidos pelo exportador. Ainda é necessário confirmar isso na origem e obter backup dos arquivos/imagens do site.

## Inventário restaurado

Todas as tabelas usam **MyISAM / utf8mb3_general_ci**.

| Tabela | Registros | Uso |
| --- | ---: | --- |
| `POST_pena` | 184 | Artigos |
| `AUTOR_pena` | 7 | Autores |
| `CATEGORIA_pena` | 8 | Categorias |
| `CATEGORIA_POST_pena` | 79 | Associações de artigos/categorias |
| `IMAGENS_pena` | 0 | Cadastro legado de imagens |
| `PESSOA_pena` | 4 | Pessoas/contas legadas |
| `LOG_pena` | 731 | Histórico legado |
| `CLIENTE_pena` | 5 | Clientes legados |
| `EMAIL_pena` | 4 | Registros de e-mail legados |
| `TEMAS_pena` | 8 | Temas legados |

Não reproduzir nomes, e-mails, senhas, hashes de senha ou recuperação de conta em documentação/logs. As tabelas `pena_admin_users` e `pena_post_order` **não existem** no backup. As quatro pessoas legadas não são automaticamente usuários da nova autenticação Laravel.

## Campos confirmados dos artigos

| Campo | Tipo/limite | Observação |
| --- | --- | --- |
| `ID_POST` | inteiro unsigned, auto incremento, PK | Preservar IDs e links antigos |
| `TITULO_POST` | varchar(71) | Limite real do título; não permitir truncamento no CRUD |
| `LINK_POST` | varchar(200), obrigatório, UNIQUE | Slug existente para URL amigável |
| `CONTEUDO_POST` | longtext | HTML legado; precisa de sanitização |
| `STATUS_POST` | SET('PE','PO','PP','PR') | Não é ENUM: validar um único estado no aplicativo |
| `SNIPPET_POST` | varchar(156) | Resumo curto |
| `DESCRICAO_POST` | text | Descrição |
| `KEYWORDS_POST` | varchar(200) | Palavras-chave |
| `URL_IMAGEM_POST` | varchar(200) | URL da capa efetivamente usada |
| `ID_IMAGENS` | inteiro unsigned, nullable | Nenhum artigo referencia um registro de mídia |
| `ID_AUTOR` | mediumint unsigned, nullable | Há valores 0 sem autor correspondente |
| `ID_PESSOA` | mediumint unsigned, obrigatório | Identificação de criação; não equivale necessariamente ao autor editorial |
| `ID_CATEGORIA` | mediumint unsigned, nullable | Categoria principal |
| `DESTAQUE_POST` | SET('S','N') | Separado da futura ordenação manual |
| `DATA_CRIACAO_POST` | datetime obrigatório | Criação |
| `DATA_POSTAGEM_POST` | datetime nullable | Publicação; preenchido em todos os registros atuais |
| `DATA_ULTIMA_MODIFICACAO_POST` | datetime nullable | Última alteração |

Os demais campos de autores, imagens e categorias podem ser consultados de forma reproduzível em `scripts/inspect-restored-database.sql`.

## URLs amigáveis

- **184 slugs preenchidos e distintos**; maior comprimento: 87 caracteres.
- **152 publicados com slugs válidos** no formato minúsculo `palavras-separadas-por-hifens`.
- Três slugs fora desse padrão, todos em artigos `PE`; um deles é uma URL absoluta. Não expor nem corrigir automaticamente os excluídos.
- A verificação usa comparação binária para não mascarar maiúsculas pela collation case-insensitive.
- Exemplo de URL proposta a partir de registro existente: `/conhecimento/dicas-para-a-construcao-de-uma-piscina-pequena`.
- Não precisamos criar outro campo de slug. A consulta por `LINK_POST` com filtro estrito `PP`, validação de rota e 404 para não publicados foi implementada localmente após esta vistoria; não foi homologada no servidor.
- Preservar slugs atuais; futuras alterações devem guardar aliases/redirecionamentos, evitando quebrar URLs antigas. Não usar URLs do campo como destino arbitrário de redirecionamento.

## Estados editoriais e inconsistências

- `PP`: **152 publicados**; `PO`: **26 ocultos**; `PE`: **6 excluídos**; nenhum `PR` presente.
- O comentário da coluna descreve `PA`, que não existe no SET, e não explica `PR`. Confirmar a semântica de `PR` antes de oferecer esse estado no painel.
- Posts **148 e 150**, ambos `PP`, têm `ID_AUTOR=0` e nenhum autor correspondente. O adaptador atual usa o nome da pessoa criadora como fallback: isso é comportamento herdado, não comprovação de autoria editorial. Definir a atribuição correta antes da publicação definitiva.
- Vínculos dos posts **10 (PE) e 11 (PO)** apontam para a categoria inexistente **9**. Não há vínculos órfãos de post, nem referências inválidas à categoria principal/pessoa/imagem dos artigos.
- Todos os 184 artigos têm conteúdo, data de publicação e URL direta de imagem preenchidos. Isso **não comprova disponibilidade HTTP** dessas imagens. A tabela `IMAGENS_pena` vazia não significa ausência de fotos no conteúdo.

## Teste Laravel contra a restauração real

`scripts/verify-restored-database.php` foi executado com a aplicação montada somente para leitura, sem rede e com trava para o socket local e banco `pena_audit`. Resultado **PASS**:

- Listagem: exatamente os 152 IDs publicados, sem duplicatas.
- Detalhes: os 152 retornam o mesmo contrato da listagem.
- Os 32 IDs não públicos não retornam conteúdo; ID inexistente também não.
- JSON válido, sem erro de codificação; slugs publicados válidos e únicos.

Isso valida o **adaptador de leitura** contra dados reais restaurados, não o CRUD, as migrações ou o comportamento HTTP em produção. A suíte PHPUnit sintética continua separada; o dump privado não deve ser enviado ao CI.

## Bloqueadores para escrita/publicação

1. **MyISAM:** não assumir rollback/transações e proteção concorrente de InnoDB. O `lockForUpdate()` atual da ordenação não deve ser tratado como garantia transacional sobre `POST_pena` MyISAM. Planejar tratamento de concorrência ou conversão revisada/testada, sem conversão automática na origem.
2. **utf8mb3:** caracteres como emojis não têm suporte completo. Não aceitar conteúdo fora da capacidade da base silenciosamente; estudar migração para utf8mb4 separadamente.
3. **Autenticação:** `PESSOA_pena` possui formato legado de senha/recuperação. Não importar credenciais às cegas para `pena_admin_users`, nem publicar esses registros.
4. Fazer backup dos arquivos e mídias atuais antes de trocar `pena`/domínio principal. Revalidar o dump perto do deploy se a origem tiver recebido novas alterações.
5. Autores, mídias, editor de posts, OpenAPI protegida e URLs por slug foram implementados e testados **localmente**, sem migrações na base real. O cadastro/edição de categorias não foi implementado. Continuam pendentes a decisão sobre escrita MyISAM, backup atualizado, homologação dos domínios/servidor e testes com a instalação real.

## Reprodução da consulta (após restaurar a base isolada)

Na raiz do repositório, nunca na hospedagem de produção:

```powershell
docker exec pena-restore-audit-20261005 bash -c 'mariadb --protocol=socket -uroot --default-character-set=utf8mb4 --batch pena_audit < /audit/inspect-restored-database.sql'
docker run --rm --network none -v "${PWD}/PENA:/app:ro" -v pena-audit-socket-20261005:/run/mysqld:ro -w /app -e APP_ENV=testing -e DB_CONNECTION=mysql -e DB_HOST=localhost -e DB_DATABASE=pena_audit -e DB_USERNAME=root -e DB_PASSWORD= -e DB_URL= -e DB_SOCKET=/run/mysqld/mysqld.sock -e CACHE_STORE=array -e SESSION_DRIVER=array pena-app php scripts/verify-restored-database.php
```

A conta root sem senha é exclusiva do contêiner temporário **sem rede e sem portas**, não uma configuração para a aplicação ou o servidor. O contêiner precisa ter o diretório de scripts em `/audit` e o volume de socket acima; ao encerrar o contêiner com tmpfs, restaurar novamente o dump antes de repetir a auditoria.
