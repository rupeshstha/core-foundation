<?php

namespace CoreFoundation\Analysis;

/**
 * MethodMetrics
 *
 * Value object carrying all computed metrics for a single PHP method.
 * Immutable once constructed — passed through the analysis pipeline read-only.
 */
final readonly class MethodMetrics
{
    public function __construct(
        /** Fully qualified class name */
        public string $class,

        /** Method name */
        public string $method,

        /** Method visibility: public, protected, private */
        public string $visibility,

        /** Absolute file path */
        public string $file,

        /** Lines of code (non-empty, non-comment lines in method body) */
        public int $loc,

        /** Number of declared parameters */
        public int $arguments,

        /** Cyclomatic complexity — decision paths through the method */
        public int $cyclomaticComplexity,

        /** Smell score: (ccn + arg) * loc */
        public int $smellScore,
    ) {}

    /**
     * Whether this method exceeds the given smell threshold.
     */
    public function exceedsThreshold(int $threshold): bool
    {
        return $this->smellScore >= $threshold;
    }

    /**
     * Short display name for table output: ClassName::method
     */
    public function displayName(): string
    {
        return class_basename($this->class).'::'.$this->method;
    }

    /** @return array<string, string|int> */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'method' => $this->method,
            'visibility' => $this->visibility,
            'file' => $this->file,
            'loc' => $this->loc,
            'arguments' => $this->arguments,
            'cyclomatic_complexity' => $this->cyclomaticComplexity,
            'smell_score' => $this->smellScore,
        ];
    }
}
