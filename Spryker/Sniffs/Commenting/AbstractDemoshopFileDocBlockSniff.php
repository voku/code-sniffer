<?php

namespace Spryker\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

abstract class AbstractDemoshopFileDocBlockSniff implements Sniff
{

    const EXPECTED_COMMENT_FIRST_LINE_STRING = 'This file is part of the Spryker Demoshop.';
    const EXPECTED_COMMENT_SECOND_LINE_STRING = 'For full license information, please view the LICENSE file that was distributed with this source code.';

    const DEMOSHOP_NAMESPACE = 'Pyz';

    /**
     * @var array
     */
    protected $sprykerTestNamespaces = [
        'Unit',
        'Functional',
        'YvesUnit',
        'YvesFunctional',
        'SharedUnit',
        'SharedFunctional',
        'ZedUnit',
        'ZedFunctional',
        'Acceptance',
    ];

    /**
     * @var array
     */
    protected $sprykerApplications = [
        'Client',
        'Shared',
        'Yves',
        'Zed',
        'Service',
    ];

    /**
     * @inheritdoc
     */
    public function register()
    {
        return [
            T_NAMESPACE
        ];
    }

    /**
     * @var bool|null
     */
    protected static $isDemoshop = null;

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     *
     * @return bool
     */
    protected function isDemoshop(File $phpCsFile)
    {
        if (static::$isDemoshop !== null) {
            return static::$isDemoshop;
        }

        $positionSprykerCore = strpos($phpCsFile->getFilename(), '/src/');
        if (!$positionSprykerCore) {
            return false;
        }

        $file = substr($phpCsFile->getFilename(), 0, $positionSprykerCore) . '/composer.json';
        if (!is_file($file)) {
            static::$isDemoshop = false;
            return static::$isDemoshop;
        }

        $content = file_get_contents($file);
        static::$isDemoshop = (bool)preg_match('#"name":\s*"spryker/(project|demoshop)"#', $content, $matches);

        return static::$isDemoshop;
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     *
     * @return bool
     */
    protected function isPyzNamespace(File $phpCsFile, $stackPointer)
    {
        $firstNamespaceTokenPosition = $phpCsFile->findNext(T_STRING, $stackPointer);
        if ($firstNamespaceTokenPosition) {
            $firstNamespaceString = $phpCsFile->getTokens()[$firstNamespaceTokenPosition]['content'];
            $secondNamespaceTokenPosition = $phpCsFile->findNext(T_STRING, $firstNamespaceTokenPosition + 1);

            if (!$secondNamespaceTokenPosition) {
                return false;
            }

            $secondNamespaceString = $phpCsFile->getTokens()[$secondNamespaceTokenPosition]['content'];

            $isSprykerClass = ($firstNamespaceString === static::DEMOSHOP_NAMESPACE && in_array($secondNamespaceString, $this->sprykerApplications));
            $isSprykerTestClass = (in_array($firstNamespaceString, $this->sprykerTestNamespaces) && $secondNamespaceString === static::DEMOSHOP_NAMESPACE);

            return ($isSprykerClass || $isSprykerTestClass);
        }

        return false;
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     *
     * @return bool
     */
    protected function existsFileDocBlock(File $phpCsFile, $stackPointer)
    {
        $fileDocBlockStartPosition = $phpCsFile->findPrevious(T_DOC_COMMENT_OPEN_TAG, $stackPointer);

        return ($fileDocBlockStartPosition !== false);
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     *
     * @return void
     */
    protected function addFileDocBlock(File $phpCsFile, $stackPointer)
    {
        $phpCsFile->fixer->beginChangeset();

        $this->clearFileDocBlock($phpCsFile, $stackPointer);

        $phpCsFile->fixer->addNewline($stackPointer);
        $phpCsFile->fixer->addContent($stackPointer, '/**');
        $phpCsFile->fixer->addNewline($stackPointer);
        $phpCsFile->fixer->addContent($stackPointer, ' * ' . static::EXPECTED_COMMENT_FIRST_LINE_STRING);
        $phpCsFile->fixer->addNewline($stackPointer);
        $phpCsFile->fixer->addContent($stackPointer, ' * ' . static::EXPECTED_COMMENT_SECOND_LINE_STRING);
        $phpCsFile->fixer->addNewline($stackPointer);
        $phpCsFile->fixer->addContent($stackPointer, ' */');
        $phpCsFile->fixer->addNewline($stackPointer);
        $phpCsFile->fixer->addNewline($stackPointer);
        $phpCsFile->fixer->endChangeset();
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     *
     * @return void
     */
    protected function clearFileDocBlock(File $phpCsFile, $stackPointer)
    {
        $fileDocBlockStartPosition = $phpCsFile->findPrevious(T_OPEN_TAG, $stackPointer) + 1;

        $currentPosition = $fileDocBlockStartPosition;
        $endPosition = $phpCsFile->findNext([T_NAMESPACE], $currentPosition);
        while ($currentPosition < $endPosition) {
            $phpCsFile->fixer->replaceToken($currentPosition, '');
            $currentPosition++;
        }
    }

}
