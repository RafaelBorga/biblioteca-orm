# BibliotecaORM

Mini-ORM didático em PHP puro com PDO e MySQL, desenvolvido para a avaliação prática de Programação II (Ciência da Computação, UNOESC). Ele persiste livros e autores de uma biblioteca usando um **Data Mapper simplificado**: as entidades não conhecem SQL, e os repositórios convertem registros do banco em objetos e vice-versa.

Não usa Composer, frameworks nem ORM de terceiros.

## Requisitos

- PHP 8.2 ou superior, com a extensão `pdo_mysql` ativa (`php -m` deve listá-la)
- MySQL 8 com InnoDB

## Estrutura

```
biblioteca-orm/
  sql/schema.sql                              tabelas livros, autores, livros_autores e exemplares
  src/Domain/Livro.php                        entidade Livro (encapsulamento e validação)
  src/Domain/Autor.php                        entidade Autor
  src/Infrastructure/RepositoryInterface.php  contrato comum: buscar, listar, salvar, excluir
  src/Infrastructure/AbstractRepository.php   CRUD reutilizável (herança e abstração)
  src/Infrastructure/LivroRepository.php      mapeamento de livros e relação N:N com autores
  src/Infrastructure/AutorRepository.php      mapeamento de autores
  src/Infrastructure/Conexao.php              conexão PDO configurada por variáveis de ambiente
  tests/smoke.php                             demonstração reproduzível e testes
```

## Como configurar o banco

1. Entre no MySQL como administrador, a partir da pasta do projeto:

   ```
   mysql -u root -p
   ```

2. Crie o banco, as tabelas e um usuário próprio para a aplicação (troque `<senha>` por uma senha sua):

   ```sql
   SOURCE sql/schema.sql;
   CREATE USER 'biblioteca'@'%' IDENTIFIED BY '<senha>';
   GRANT ALL PRIVILEGES ON biblioteca_orm.* TO 'biblioteca'@'%';
   ```

## Como executar

A conexão lê as credenciais das variáveis de ambiente `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS`. Nenhuma senha fica gravada no código.

No PowerShell (Windows):

```powershell
$env:DB_HOST="127.0.0.1"
$env:DB_NAME="biblioteca_orm"
$env:DB_USER="biblioteca"
$env:DB_PASS="<senha>"
php tests\smoke.php
```

No Linux/macOS:

```bash
DB_HOST=127.0.0.1 DB_NAME=biblioteca_orm DB_USER=biblioteca DB_PASS='<senha>' php tests/smoke.php
```

A saída esperada termina com `Todos os testes passaram.` O script grava registros novos a cada execução; recomenda-se usar um banco de testes.

## O que a demonstração mostra

**Parte A — evidências de execução da avaliação:**

| Evidência | O que é demonstrado |
| --- | --- |
| 1 | Cadastro de Livro e Autor com IDs gerados pelo MySQL |
| 2 | Busca por ID com hidratação (tipos preservados), SELECT de Autor e listagem |
| 3 | Atualização do título confirmada por nova consulta |
| 4 | Dois autores associados ao mesmo livro e reconstrução da coleção sem duplicar objetos |
| 5 | Mesma função usando `LivroRepository` e `AutorRepository` pelo contrato `RepositoryInterface` |
| 6 | Erro intencional dentro de uma transação e rollback |
| 7 | Exclusão bloqueada pela chave estrangeira e exclusão controlada após remover os vínculos |

**Parte B — testes da seção 11 do artigo:** inserção, busca existente e inexistente, alteração, exclusão, vínculo autor-livro, rollback, título vazio, tentativa de SQL injection, contrato polimórfico e vínculo N:N duplicado.

## Decisões de projeto

- **Data Mapper em vez de Active Record:** `Livro` e `Autor` não recebem conexão nem executam SQL; a persistência fica nos repositórios.
- **Classe abstrata com CRUD comum:** `AbstractRepository` implementa buscar, listar, salvar e excluir; as subclasses informam tabela, colunas, hidratação e extração.
- **Identificadores SQL vêm só do código:** nomes de tabela e coluna são definidos pelos repositórios; valores externos passam sempre por prepared statements.
- **Relacionamento N:N explícito:** os autores de um livro são carregados por `autoresDoLivro()` e os vínculos são gravados por `associarAutor()` e removidos por `removerAutores()`.

## Limitações conhecidas

- Sem carregamento automático (lazy loading) de relacionamentos, identity map, migrations ou query builder.
- `salvar()` decide entre INSERT e UPDATE apenas pela existência do ID; não há controle de concorrência.
- Não há exclusão em cascata: livros e autores vinculados precisam ter os vínculos removidos antes.
- Funciona apenas para tabelas com chave primária inteira chamada `id`.
- A tabela `exemplares` existe no banco, mas não possui entidade nem repositório.
