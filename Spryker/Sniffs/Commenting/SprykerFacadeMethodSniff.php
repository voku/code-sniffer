<?php

namespace Spryker\Sniffs\Commenting;

use PHP_CodeSniffer_File;
use PHP_CodeSniffer_Sniff;
use Spryker\Sniffs\AbstractSniffs\AbstractSprykerSniff;
use Spryker\Tools\Traits\CommentingTrait;

/**
 * Checks if doc blocks of Spryker facade methods match doc blocks of their interfaces.
 */
class SprykerFacadeMethodSniff extends AbstractSprykerSniff
{

    use CommentingTrait;

    /**
     * @return array
     */
    public function register()
    {
        return [
            T_FUNCTION,
        ];
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $stackPointer
     *
     * @return void
     */
    public function process(\PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        if (!$this->isSprykerFacadeApiClass($phpCsFile, $stackPointer)) {
            return;
        }

        if ($this->isFacadeInterface($phpCsFile, $stackPointer)) {
            $this->checkInterface($phpCsFile, $stackPointer);
            return;
        }

        $this->checkFacade($phpCsFile, $stackPointer);
    }

    /**
     * Facades must have a matching interface.
     *
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $stackPointer
     *
     * @return void
     */
    protected function checkFacade(PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        //$name = $this->getClassOrInterfaceName($phpCsFile, $stackPointer);
        $methodName = $this->getMethodName($phpCsFile, $stackPointer);

        $facadeInterfaceFile = str_replace('Facade.php', 'FacadeInterface.php', $phpCsFile->getFilename());

        if (!file_exists($facadeInterfaceFile)) {
            // Let another sniff take care of it
            return;
        }

        $docBlockEndIndex = $this->findRelatedDocBlock($phpCsFile, $stackPointer);
        if (!$docBlockEndIndex) {
            return;
        }

        $tokens = $phpCsFile->getTokens();

        $docBlockStartIndex = $tokens[$docBlockEndIndex]['comment_opener'];

        $hasInheritDoc = $this->hasInheritDoc($phpCsFile, $docBlockStartIndex, $docBlockEndIndex);
        if ($hasInheritDoc) {
            return;
        }

        $phpCsFile->addError('The interface method `' . $methodName . '()` must contain the specifications, not the Facade one', $docBlockStartIndex);
    }

    /**
     * Facade Interfaces must have a "Specification" block as part of the contract.
     *
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $stackPointer
     *
     * @return void
     */
    protected function checkInterface(PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        $methodName = $this->getMethodName($phpCsFile, $stackPointer);

        $interfaceSpecification = $this->findInterfaceSpecification($phpCsFile, $stackPointer, $methodName);
        if (!$interfaceSpecification) {
            $phpCsFile->addError(sprintf('FacadeInterface does not contain specifications for method `%s()`', $methodName), $stackPointer);
            return;
        };

        $facadeSpecification = $this->findFacadeSpecification($phpCsFile, $methodName);
        if (!$facadeSpecification) {
            die($methodName);
            return;
        }

        if ($facadeSpecification !== $interfaceSpecification) {
            var_dump($facadeSpecification); var_dump($interfaceSpecification);

            $phpCsFile->addError(
                sprintf('Specification for Facade method `%s()` exists, but does not match Interface', $methodName),
                $stackPointer
            );
        }
    }

    /**
     * @param PHP_CodeSniffer_File $phpCsFile
     * @param int $stackPointer
     * @param string $methodName
     * @return string|null
     */
    protected function findInterfaceSpecification(PHP_CodeSniffer_File $phpCsFile, $stackPointer, $methodName)
    {
        $tokens = $phpCsFile->getTokens();

        $docBlockEndIndex = $this->findRelatedDocBlock($phpCsFile, $stackPointer);
        if (!$docBlockEndIndex) {
            return null;
        }
        $docBlockStartIndex = $tokens[$docBlockEndIndex]['comment_opener'];

        $docBlockContent = '';
        for ($i = $docBlockStartIndex + 1; $i < $docBlockEndIndex; $i++) {
            $docBlockContent .= $tokens[$i]['content'];
        }

        preg_match('#Specification:(.+?)\* \@api#msi', $docBlockContent, $matches);

        if (empty($matches[1])) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * @param PHP_CodeSniffer_File $phpCsFile
     * @param string $methodName
     *
     * @return string|null
     */
    protected function findFacadeSpecification(PHP_CodeSniffer_File $phpCsFile, $methodName)
    {
        $facadeFile = str_replace('FacadeInterface.php', 'Facade.php', $phpCsFile->getFilename());

        $content = file_get_contents($facadeFile);
        //preg_match('#\/\*\*[^@]+\* \@api[^:]+public function ' . $methodName . '\(#msi', $content, $matches);

        preg_match('#Specification:[^:]+\* \@api[^:]+public function ' . $methodName . '\(#msi', $content, $matches);
var_dump($matches);
        if (empty($matches[1])) {
            return null;
        }

        $facadeSpecification = trim($matches[1]);

        return $facadeSpecification;
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $stackPointer
     *
     * @return bool
     */
    protected function getMethodName(\PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        $tokens = $phpCsFile->getTokens();

        $classOrInterfaceNamePosition = $phpCsFile->findNext(T_STRING, $stackPointer);

        return $tokens[$classOrInterfaceNamePosition]['content'];
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $stackPointer
     *
     * @return bool
     */
    protected function isSprykerFacadeApiClass(\PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        if (!$this->hasNamespace($phpCsFile, $stackPointer)) {
            return false;
        }

        $namespace = $this->getCurrentNamespace($phpCsFile, $stackPointer);
        $name = $this->getClassOrInterfaceName($phpCsFile, $stackPointer);
        if (!$name || $name === 'AbstractFacade') {
            return false;
        }

        if (preg_match('/^Spryker\\\\Zed\\\\(.*?)\\\\Business$/', $namespace) && preg_match('/^(.+?)(Facade|FacadeInterface)$/', $name)) {
            return true;
        }

        return false;
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $stackPointer
     *
     * @return bool
     */
    protected function hasNamespace(\PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        $namespacePosition = $phpCsFile->findPrevious(T_NAMESPACE, $stackPointer);
        if (!$namespacePosition) {
            return false;
        }

        return true;
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $stackPointer
     *
     * @return string
     */
    protected function getCurrentNamespace(\PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        $namespacePosition = $phpCsFile->findPrevious(T_NAMESPACE, $stackPointer);
        $endOfNamespacePosition = $phpCsFile->findEndOfStatement($namespacePosition);

        $tokens = $phpCsFile->getTokens();
        $namespaceTokens = array_splice($tokens, $namespacePosition + 2, $endOfNamespacePosition - $namespacePosition - 2);

        $namespace = '';
        foreach ($namespaceTokens as $token) {
            $namespace .= $token['content'];
        }

        return $namespace;
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $stackPointer
     *
     * @return string
     */
    protected function getClassOrInterfaceName(\PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        $classOrInterfacePosition = $phpCsFile->findPrevious([T_CLASS, T_INTERFACE], $stackPointer);
        $classOrInterfaceNamePosition = $phpCsFile->findNext(T_STRING, $classOrInterfacePosition);

        $tokens = $phpCsFile->getTokens();

        return $tokens[$classOrInterfaceNamePosition]['content'];
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $stackPointer
     *
     * @return bool
     */
    protected function isFacadeInterface($phpCsFile, $stackPointer)
    {
        $namespace = $this->getCurrentNamespace($phpCsFile, $stackPointer);
        $name = $this->getClassOrInterfaceName($phpCsFile, $stackPointer);

        if (preg_match('/^Spryker\\\\Zed\\\\(.+?)\\\\Business$/', $namespace) && preg_match('/^(.+?)(FacadeInterface)$/', $name)) {
            return true;
        }

        return false;
    }

}
