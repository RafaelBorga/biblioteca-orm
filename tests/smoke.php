<?php
// tests/smoke.php
// Demonstração reproduzível da BibliotecaORM:
//   Parte A — as 7 evidências de execução exigidas na avaliação (seção 4)
//   Parte B — os testes da seção 11 do artigo/tutorial
// Uso (PowerShell, dentro da pasta biblioteca-orm):
//   $env:DB_HOST="127.0.0.1"; $env:DB_NAME="biblioteca_orm"; $env:DB_USER="biblioteca"; $env:DB_PASS="..."
//   php tests\smoke.php

use Biblioteca\Domain\Autor;
use Biblioteca\Domain\Livro;
use Biblioteca\Infrastructure\AutorRepository;
use Biblioteca\Infrastructure\Conexao;
use Biblioteca\Infrastructure\LivroRepository;
use Biblioteca\Infrastructure\RepositoryInterface;

// ---------------------------------------------------------------
// Autoloader: Biblioteca\X\Y -> src/X/Y.php
// ---------------------------------------------------------------
spl_autoload_register(function (string $classe): void {
    $prefixo = 'Biblioteca\\';
    if (strncmp($classe, $prefixo, strlen($prefixo)) !== 0) return;
    $relativo = substr($classe, strlen($prefixo));
    $arquivo = __DIR__ . '/../src/' . str_replace('\\', '/', $relativo) . '.php';
    if (is_file($arquivo)) require_once $arquivo;
});

// ---------------------------------------------------------------
// Funções auxiliares
// ---------------------------------------------------------------
$falhas = 0;

// Teste em que o certo é a condição ser verdadeira
function ok(bool $condicao, string $teste): void {
    global $falhas;
    if (!$condicao) $falhas++;
    echo ($condicao ? '[OK]     ' : '[FALHOU] ') . $teste . PHP_EOL;
}

// Teste em que o certo é dar erro (lançar exceção)
function deveFalhar(callable $acao, string $teste): void {
    global $falhas;
    try {
        $acao();
        $falhas++;
        echo "[FALHOU] $teste (nenhuma exceção foi lançada)" . PHP_EOL;
    } catch (\Throwable $e) {
        echo "[OK]     $teste -> " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    }
}

// Mostra uma informação da execução (para as evidências do relatório)
function info(string $texto): void {
    echo '         ' . $texto . PHP_EOL;
}

function secao(string $titulo): void {
    echo PHP_EOL . "--- $titulo ---" . PHP_EOL;
}

// Código consumidor polimórfico: recebe qualquer RepositoryInterface,
// sem testar qual é a classe concreta (requisito R5)
function descrever(RepositoryInterface $repo, int $id): string {
    $entidade = $repo->buscar($id);
    return $entidade === null ? 'não encontrado' : get_class($entidade) . " #$id";
}

// ---------------------------------------------------------------
// Conexão
// ---------------------------------------------------------------
try {
    $pdo = Conexao::abrir();
} catch (\PDOException $e) {
    echo 'Não foi possível conectar ao banco: ' . $e->getMessage() . PHP_EOL;
    echo 'Confira as variáveis DB_HOST, DB_NAME, DB_USER, DB_PASS e se o MySQL está rodando na porta 3306.' . PHP_EOL;
    exit(1);
}
$livros = new LivroRepository($pdo);
$autores = new AutorRepository($pdo);

echo '===== PARTE A — Evidências de execução (avaliação, seção 4) =====' . PHP_EOL;

// ---------------------------------------------------------------
// Evidência 1 — Cadastro de Livro e Autor com IDs gerados pelo MySQL
// ---------------------------------------------------------------
secao('Evidência 1: cadastro de Livro e Autor');
$autorA = new Autor('Autora Exemplo', 'Brasil');
$autores->salvar($autorA);
$livro = new Livro('POO e Banco de Dados', null, 2026);
$livros->salvar($livro);
info('Autor salvo com ID ' . $autorA->id() . '; Livro salvo com ID ' . $livro->id());
ok($autorA->id() > 0 && $livro->id() > 0, 'IDs gerados pelo MySQL (AUTO_INCREMENT)');

// ---------------------------------------------------------------
// Evidência 2 — Busca de Livro por ID e reconstrução (hidratação)
// ---------------------------------------------------------------
secao('Evidência 2: busca por ID e hidratação');
$recuperado = $livros->buscar($livro->id());
info('Classe: ' . get_class($recuperado) . ' | id: ' . var_export($recuperado->id(), true)
    . ' | título: ' . $recuperado->titulo() . ' | ano: ' . var_export($recuperado->anoPublicacao(), true));
ok($recuperado instanceof Livro && $recuperado->id() === $livro->id() && $recuperado->anoPublicacao() === 2026,
    'Livro reconstruído com tipos preservados (id e ano como int)');
$autorRecuperado = $autores->buscar($autorA->id());
ok($autorRecuperado instanceof Autor && $autorRecuperado->nome() === 'Autora Exemplo', 'SELECT por ID de Autor');
$lista = $livros->listar();
info('listar() devolveu ' . count($lista) . ' livro(s)');
ok(count($lista) >= 1 && $lista[0] instanceof Livro, 'Listagem de livros');

// ---------------------------------------------------------------
// Evidência 3 — Atualização e nova consulta
// ---------------------------------------------------------------
secao('Evidência 3: atualização');
$recuperado->alterarTitulo('POO, PDO e MySQL');
$livros->salvar($recuperado);
$novaConsulta = $livros->buscar($livro->id());
info('Título antes: POO e Banco de Dados | depois: ' . $novaConsulta->titulo());
ok($novaConsulta->titulo() === 'POO, PDO e MySQL', 'UPDATE confirmado por nova consulta');

// ---------------------------------------------------------------
// Evidência 4 — Dois autores no mesmo livro e recuperação da coleção
// ---------------------------------------------------------------
secao('Evidência 4: relacionamento N:N com dois autores');
$autorB = new Autor('Autor Convidado', 'Portugal');
$autores->salvar($autorB);
$livros->associarAutor($livro->id(), $autorA->id());
$livros->associarAutor($livro->id(), $autorB->id());
$livroComAutores = $livros->buscar($livro->id());
foreach ($livros->autoresDoLivro($livro->id()) as $a) $livroComAutores->adicionarAutor($a);
// Repete o carregamento: adicionarAutor() não pode duplicar objetos
foreach ($livros->autoresDoLivro($livro->id()) as $a) $livroComAutores->adicionarAutor($a);
$nomes = array_map(fn(Autor $a) => $a->nome(), $livroComAutores->autores());
info('Autores de "' . $livroComAutores->titulo() . '": ' . implode(', ', $nomes));
ok(count($livroComAutores->autores()) === 2, 'Coleção com 2 autores, sem duplicar no recarregamento');

// ---------------------------------------------------------------
// Evidência 5 — Polimorfismo pelo mesmo contrato
// ---------------------------------------------------------------
secao('Evidência 5: polimorfismo');
$viaLivros = descrever($livros, $livro->id());
$viaAutores = descrever($autores, $autorA->id());
info("descrever(LivroRepository) -> $viaLivros");
info("descrever(AutorRepository) -> $viaAutores");
ok(str_starts_with($viaLivros, Livro::class) && str_starts_with($viaAutores, Autor::class),
    'Mesma função opera com os dois repositórios via RepositoryInterface');

// ---------------------------------------------------------------
// Evidência 6 — Erro intencional em transação e rollback
// ---------------------------------------------------------------
secao('Evidência 6: transação e rollback');
$idRollback = null;
try {
    $pdo->beginTransaction();
    $livroRollback = new Livro('Livro do rollback');
    $livros->salvar($livroRollback);
    $idRollback = $livroRollback->id();
    $livros->associarAutor($livroRollback->id(), 999999999); // autor inexistente: erro proposital
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    info('Erro capturado: ' . $e->getMessage());
}
info("Livro $idRollback foi inserido dentro da transação; depois do rollback a busca devolve: "
    . var_export($livros->buscar($idRollback), true));
ok($idRollback !== null && $livros->buscar($idRollback) === null, 'Rollback desfez o livro inserido');

// ---------------------------------------------------------------
// Evidência 7 — Exclusão controlada e restrição de FK
// ---------------------------------------------------------------
secao('Evidência 7: exclusão controlada');
deveFalhar(fn() => $livros->excluir($livro->id()), 'Excluir livro ainda vinculado é bloqueado pela FK');
$livros->removerAutores($livro->id());
$livros->excluir($livro->id());
ok($livros->buscar($livro->id()) === null, 'Após remover os vínculos, o livro é excluído');
$autores->excluir($autorB->id());
ok($autores->buscar($autorB->id()) === null, 'Autor sem vínculos é excluído');

// ---------------------------------------------------------------
// PARTE B — Testes da seção 11 do artigo
// ---------------------------------------------------------------
echo PHP_EOL . '===== PARTE B — Testes da seção 11 do artigo =====' . PHP_EOL;

// 1. Inserção e ID gerado
$livroTeste = new Livro('Livro de teste');
$livros->salvar($livroTeste);
ok($livroTeste->id() !== null && $livroTeste->id() > 0, '1. Inserção gera ID');

// 2. Busca existente / inexistente
ok($livros->buscar($livroTeste->id()) instanceof Livro, '2a. Busca de livro existente');
ok($livros->buscar(999999999) === null, '2b. Busca de livro inexistente devolve null');

// 3. Alteração de título
$livroTeste->alterarTitulo('Livro de teste alterado');
$livros->salvar($livroTeste);
ok($livros->buscar($livroTeste->id())->titulo() === 'Livro de teste alterado', '3. Alteração de título');

// 4. Exclusão
$livroExcluir = new Livro('Livro para excluir');
$livros->salvar($livroExcluir);
$livros->excluir($livroExcluir->id());
ok($livros->buscar($livroExcluir->id()) === null, '4. Exclusão de livro');

// 5. Autor com livro
$autorTeste = new Autor('Autor de teste', 'Brasil');
$autores->salvar($autorTeste);
$livros->associarAutor($livroTeste->id(), $autorTeste->id());
$vinculados = $livros->autoresDoLivro($livroTeste->id());
ok(count($vinculados) === 1 && $vinculados[0]->id() === $autorTeste->id(), '5. Autor associado ao livro');

// 6. FK inválida e rollback (já demonstrado na Evidência 6)
ok($idRollback !== null && $livros->buscar($idRollback) === null, '6. FK inválida desfaz o livro (rollback)');

// 7. Rejeição de título vazio
deveFalhar(fn() => new Livro('   '), '7. Título vazio é rejeitado');

// 8. Tentativa de SQL injection em campo de texto
$tituloMalicioso = "x'); DROP TABLE livros; --";
$livroInjection = new Livro($tituloMalicioso);
$livros->salvar($livroInjection);
ok($livros->buscar($livroInjection->id())->titulo() === $tituloMalicioso, '8a. Texto malicioso salvo como texto comum');
ok($pdo->query("SHOW TABLES LIKE 'livros'")->fetch() !== false, '8b. Tabela livros continua existindo');

// 9. Contrato polimórfico (função do artigo, seção 8)
$viaContratoLivro = \Biblioteca\Infrastructure\consultarPorContrato($livros, $livroTeste->id());
$viaContratoAutor = \Biblioteca\Infrastructure\consultarPorContrato($autores, $autorTeste->id());
ok($viaContratoLivro instanceof Livro && $viaContratoAutor instanceof Autor, '9. Contrato polimórfico');

// 10. Duplicação de vínculo N:N
deveFalhar(fn() => $livros->associarAutor($livroTeste->id(), $autorTeste->id()), '10. Vínculo N:N duplicado é rejeitado');

// Extra: excluir autor que tem livro vinculado (FK sem ON DELETE CASCADE)
deveFalhar(fn() => $autores->excluir($autorTeste->id()), 'Extra. Exclusão de autor vinculado é rejeitada');

// ---------------------------------------------------------------
// Resumo
// ---------------------------------------------------------------
echo PHP_EOL . ($falhas === 0 ? 'Todos os testes passaram.' : "$falhas teste(s) falharam.") . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
