<?php

namespace Limenet\LaravelBaseline\PhpFile;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class PhpFileWriter
{
    /** @var Node\Stmt[] Mutable AST — modify this, then call save() */
    public array $stmts;

    private function __construct(
        private readonly string $path,
        private readonly array $originalStmts,
        private readonly array $tokens,
        array $stmts,
    ) {
        $this->stmts = $stmts;
    }

    /**
     * Open a PHP file for format-preserving modification.
     *
     * Exposes $stmts for mutation; call save() to write back only the changed parts.
     */
    public static function open(string $path, string $fallback = "<?php\n"): self
    {
        $source = file_exists($path) ? (string) file_get_contents($path) : $fallback;

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $originalStmts = $parser->parse($source) ?? [];
        $tokens = $parser->getTokens();

        $traverser = new NodeTraverser(new CloningVisitor());
        $stmts = $traverser->traverse($originalStmts);

        return new self($path, $originalStmts, $tokens, $stmts);
    }

    /**
     * Write a PHP config array to a file using clean multi-line formatting.
     *
     * Rebuilds the entire file from the given array. Use for machine-managed
     * config files (e.g., config/baseline.php) where the whole content is derived
     * from a PHP array.
     *
     * @param array<string, mixed> $config
     */
    public static function writeConfig(string $path, array $config): void
    {
        $printer = new class extends Standard {
            /** @param Node[] $nodes */
            protected function pMaybeMultiline(array $nodes, bool $trailingComma = false): string
            {
                if ($nodes === []) {
                    return '';
                }

                return $this->pCommaSeparatedMultiline($nodes, $trailingComma).$this->nl;
            }
        };

        $stmts = [new Return_(self::buildArrayNode($config))];

        file_put_contents($path, $printer->prettyPrintFile($stmts)."\n");
    }

    /**
     * Add use-statement imports that are not already present in the file.
     *
     * Handles both namespaced and global files. Inserts after the last existing
     * use-statement, or at the top of the statement list if none exist.
     *
     * @param list<string> $imports Fully-qualified class names to add if missing
     */
    public function addMissingUseStatements(array $imports): void
    {
        if ($imports === []) {
            return;
        }

        // In namespaced files, use-statements live inside the Namespace_ node
        $target = &$this->stmts;
        foreach ($this->stmts as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $target = &$node->stmts;
                break;
            }
        }

        $existingFqns = [];
        $lastUseIdx = -1;

        foreach ($target as $i => $stmt) {
            if ($stmt instanceof Node\Stmt\Use_) {
                $lastUseIdx = $i;
                foreach ($stmt->uses as $use) {
                    $existingFqns[] = $use->name->toString();
                }
            }
        }

        $newUses = [];
        foreach ($imports as $fqn) {
            if (!in_array($fqn, $existingFqns, true)) {
                $newUses[] = new Node\Stmt\Use_([new Node\UseItem(new Node\Name($fqn))]);
            }
        }

        if ($newUses !== []) {
            $insertIdx = $lastUseIdx >= 0 ? $lastUseIdx + 1 : 0;
            array_splice($target, $insertIdx, 0, $newUses);
        }
    }

    /**
     * Write $stmts back to the file, preserving all formatting that was not changed.
     */
    public function save(): void
    {
        file_put_contents(
            $this->path,
            (new Standard)->printFormatPreserving($this->stmts, $this->originalStmts, $this->tokens),
        );
    }

    /** @param array<mixed> $array */
    private static function buildArrayNode(array $array): Array_
    {
        $isList = array_is_list($array);
        $items = [];

        foreach ($array as $key => $value) {
            $keyNode = $isList ? null : new String_((string) $key);
            $valueNode = is_array($value) ? self::buildArrayNode($value) : new String_((string) $value);
            $items[] = new ArrayItem($valueNode, $keyNode);
        }

        return new Array_($items, ['kind' => Array_::KIND_SHORT]);
    }
}
