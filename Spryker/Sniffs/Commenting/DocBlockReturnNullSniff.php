<?php

namespace Spryker\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use Spryker\Traits\BasicsTrait;

/**
 * Doc blocks should type-hint returning null for nullable return values (if null is used besides other return values).
 */
class DocBlockReturnNullSniff implements Sniff
{

    use BasicsTrait;

    /**
     * @inheritdoc
     */
    public function register()
    {
        return [
            T_FUNCTION,
        ];
    }

    /**
     * @inheritdoc
     */
    public function process(File $phpCsFile, $stackPointer)
    {
        $tokens = $phpCsFile->getTokens();

        // Don't mess with closures
        $prevIndex = $phpCsFile->findPrevious(Tokens::$emptyTokens, $stackPointer - 1, null, true);
        if (!$this->isGivenKind(Tokens::$methodPrefixes, $tokens[$prevIndex])) {
            return;
        }

        $docBlockEndIndex = $this->findRelatedDocBlock($phpCsFile, $stackPointer);

        if (!$docBlockEndIndex) {
            return;
        }

        $returnTypes = $this->extractReturnTypes($phpCsFile, $stackPointer);
        if (!$returnTypes) {
            return;
        }
        if (count($returnTypes) === 2 && in_array('', $returnTypes, true) && in_array('null', $returnTypes, true)) {
            $phpCsFile->addError('Void mixed with null is discouraged, use only `null` instead', $docBlockEndIndex, 'NullVoidMixed');
            return;
        }
        if (count($returnTypes)  > 1 && in_array('', $returnTypes, true)) {
            $phpCsFile->addWarning('Void mixed with other return types is discouraged, use `null` instead', $docBlockEndIndex, 'InvalidVoid');
            return;
        }

        $docBlockStartIndex = $tokens[$docBlockEndIndex]['comment_opener'];

        for ($i = $docBlockStartIndex + 1; $i < $docBlockEndIndex; $i++) {
            if ($tokens[$i]['type'] !== 'T_DOC_COMMENT_TAG') {
                continue;
            }
            if (!in_array($tokens[$i]['content'], ['@return'])) {
                continue;
            }

            $classNameIndex = $i + 2;

            if ($tokens[$classNameIndex]['type'] !== 'T_DOC_COMMENT_STRING') {
                continue;
            }

            $content = $tokens[$classNameIndex]['content'];

            $appendix = '';
            $spaceIndex = strpos($content, ' ');
            if ($spaceIndex) {
                $appendix = substr($content, $spaceIndex);
                $content = substr($content, 0, $spaceIndex);
            }

            if (empty($content)) {
                continue;
            }

            $parts = explode('|', $content);
            $this->fixParts($phpCsFile, $classNameIndex, $returnTypes, $parts, $appendix);
        }
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $classNameIndex
     * @param array $returnTypes
     * @param array $parts
     * @param string $appendix
     *
     * @return void
     */
    protected function fixParts(File $phpCsFile, $classNameIndex, array $returnTypes, array $parts, $appendix)
    {
        if (!in_array('null', $returnTypes) || in_array('null', $parts)) {
            // For now only "return null", later we can add all values to comparison
            return;
        }

        $newParts = $parts;
        $newParts[] = 'null';

        $newContent = implode('|', $newParts);

        $fix = $phpCsFile->addFixableError('Missing nullable type in `' . implode('|', $parts) . '` return annotation, expected `' . $newContent . '`', $classNameIndex, 'MissingNullable');
        if ($fix) {
            $phpCsFile->fixer->replaceToken($classNameIndex, $newContent . $appendix);
        }
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     *
     * @return int|null Stackpointer value of docblock end tag, or null if cannot be found
     */
    protected function findRelatedDocBlock(File $phpCsFile, $stackPointer)
    {
        $tokens = $phpCsFile->getTokens();

        $line = $tokens[$stackPointer]['line'];
        $beginningOfLine = $stackPointer;
        while (!empty($tokens[$beginningOfLine - 1]) && $tokens[$beginningOfLine - 1]['line'] === $line) {
            $beginningOfLine--;
        }

        if (!empty($tokens[$beginningOfLine - 2]) && $tokens[$beginningOfLine - 2]['type'] === 'T_DOC_COMMENT_CLOSE_TAG') {
            return $beginningOfLine - 2;
        }

        return null;
    }

    /**
     * For right now we only try to detect basic types.
     *
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $index
     *
     * @return array
     */
    protected function extractReturnTypes(File $phpCsFile, $index)
    {
        $tokens = $phpCsFile->getTokens();

        if (empty($tokens[$index]['scope_opener']) || empty($tokens[$index]['scope_closer'])) {
            return [];
        }

        $types = [];

        $methodStartIndex = $tokens[$index]['scope_opener'];
        $methodEndIndex = $tokens[$index]['scope_closer'];

        for ($i = $methodStartIndex + 1; $i < $methodEndIndex; ++$i) {
            if ($this->isGivenKind([T_FUNCTION, T_CLOSURE], $tokens[$i])) {
                $endIndex = $tokens[$i]['scope_closer'];
                if (!empty($tokens[$i]['nested_parenthesis'])) {
                    $endIndex = array_pop($tokens[$i]['nested_parenthesis']);
                }

                $i = $endIndex;
                continue;
            }

            if (!$this->isGivenKind([T_RETURN], $tokens[$i])) {
                continue;
            }

            $nextIndex = $phpCsFile->findNext(Tokens::$emptyTokens, $i + 1, null, true);
            $lastIndex = $phpCsFile->findNext(T_SEMICOLON, $nextIndex);

            $type = '';
            for ($i = $nextIndex; $i < $lastIndex; $i++) {
                $type .= $tokens[$i]['content'];
            }

            if (in_array($type, $types, true)) {
                continue;
            }
            $types[] = $type;
        }

        return $types;
    }

}
