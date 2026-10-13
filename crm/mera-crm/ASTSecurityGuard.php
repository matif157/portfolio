<?php
/**
 * =====================================================
 * FILE: ASTSecurityGuard.php
 * PURPOSE: Parse PHP code into AST and block dangerous nodes
 * =====================================================
 */

require_once __DIR__ . '/vendor/autoload.php';

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

class ASTSecurityGuard
{
    /**
     * List of completely forbidden function names.
     * Why: These can lead to RCE, file destruction, or data exfiltration.
     */
    private array $forbiddenFunctions = [
        // Process execution
        'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen',
        'pcntl_exec', 'proc_nice', 'proc_terminate', 'proc_close',

        // Code evaluation
        'eval', 'assert', 'create_function',

        // File system destructive / write
        'unlink', 'rmdir', 'rename', 'copy', 'move_uploaded_file',
        'file_put_contents', 'fwrite', 'fputs', 'fputcsv',
        'mkdir', 'touch', 'chmod', 'chown', 'link', 'symlink',

        // Dangerous includes
        'include', 'include_once', 'require', 'require_once',

        // Network / info leaks
        'curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen',
        'stream_socket_client', 'mail',

        // Others
        'extract', 'parse_str', 'mb_parse_str',
        'putenv', 'ini_set', 'dl', 'apache_setenv'
    ];

    /**
     * Forbidden static method calls / class usage patterns
     */
    private array $forbiddenClasses = [
        'ReflectionFunction', 'ReflectionClass', 'ReflectionMethod'
    ];

    private array $violations = [];

    /**
     * Analyze code and return result array
     */
    public function analyze(string $code): array
    {
        $this->violations = [];

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (Error $e) {
            return [
                'safe'  => false,
                'error' => 'Parse Error: ' . $e->getMessage(),
                'ast'   => null
            ];
        }

        if ($ast === null) {
            return [
                'safe'  => false,
                'error' => 'Unable to generate AST',
                'ast'   => null
            ];
        }

        // Traverse AST with our security visitor
        $traverser = new NodeTraverser();
        $visitor   = new class($this->forbiddenFunctions, $this->forbiddenClasses, $this->violations) extends NodeVisitorAbstract {
            private array $forbiddenFunctions;
            private array $forbiddenClasses;
            private array $violations;

            public function __construct(array $funcs, array $classes, array &$violations)
            {
                $this->forbiddenFunctions = $funcs;
                $this->forbiddenClasses   = $classes;
                $this->violations         = &$violations;
            }

            public function enterNode(Node $node)
            {
                // 1. Function calls: shell_exec(), eval(), etc.
                if ($node instanceof Node\Expr\FuncCall) {
                    if ($node->name instanceof Node\Name) {
                        $name = strtolower($node->name->toString());
                        if (in_array($name, $this->forbiddenFunctions, true)) {
                            $line = $node->getStartLine();
                            $this->violations[] = "Forbidden function `{$name}()` detected on line {$line}";
                        }
                    }
                }

                // 2. eval / assert as language constructs are already covered,
                //    but also block dynamic variable functions: $fn()
                if ($node instanceof Node\Expr\FuncCall && !($node->name instanceof Node\Name)) {
                    $line = $node->getStartLine();
                    $this->violations[] = "Dynamic function call detected on line {$line} (blocked for safety)";
                }

                // 3. Backtick execution: `ls`
                if ($node instanceof Node\Expr\ShellExec) {
                    $line = $node->getStartLine();
                    $this->violations[] = "Shell execution via backticks detected on line {$line}";
                }

                // 4. include / require
                if ($node instanceof Node\Expr\Include_) {
                    $line = $node->getStartLine();
                    $this->violations[] = "include/require is blocked on line {$line}";
                }

                // 5. Dangerous new Class
                if ($node instanceof Node\Expr\New_ && $node->class instanceof Node\Name) {
                    $className = $node->class->toString();
                    if (in_array($className, $this->forbiddenClasses, true)) {
                        $line = $node->getStartLine();
                        $this->violations[] = "Forbidden class `{$className}` on line {$line}";
                    }
                }
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        // Pretty print AST for inspector
        $printer = new PrettyPrinter();
        $astDump = $this->dumpAstReadable($ast);

        if (!empty($this->violations)) {
            return [
                'safe'       => false,
                'error'      => implode("\n", $this->violations),
                'ast'        => $astDump,
                'violations' => $this->violations
            ];
        }

        return [
            'safe' => true,
            'ast'  => $astDump,
            'error'=> null
        ];
    }

    /**
     * Create a simple readable AST dump
     */
    private function dumpAstReadable(array $nodes, int $depth = 0): string
    {
        $out = '';
        $pad = str_repeat('  ', $depth);

        foreach ($nodes as $node) {
            if (!$node instanceof Node) continue;

            $type = $node->getType();
            $line = $node->getStartLine();
            $out .= "{$pad}[{$type}] (line {$line})\n";

            foreach ($node->getSubNodeNames() as $name) {
                $sub = $node->$name;
                if ($sub instanceof Node) {
                    $out .= $this->dumpAstReadable([$sub], $depth + 1);
                } elseif (is_array($sub)) {
                    $out .= $this->dumpAstReadable($sub, $depth + 1);
                }
            }
        }
        return $out;
    }
}