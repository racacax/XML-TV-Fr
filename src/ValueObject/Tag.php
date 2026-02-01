<?php

namespace racacax\XmlTv\ValueObject;

class Tag
{
    private string $name;
    private array $attributes;

    /*
     * @var array<string, array<Tag>>|string
     */
    private array|string|null $value;

    private array $sortedChildren;

    /**
     * @param string $name
     * @param array<string, array<Tag>>|string|null $value
     * @param array<string, string> $attributes
     * @param array<int, string> $sortedChildren
     */
    public function __construct(string $name, array|string|null $value = null, array $attributes = [], array $sortedChildren = [])
    {
        $this->name = $name;
        $this->attributes = $attributes;
        $this->value = $value;
        $this->sortedChildren = $sortedChildren;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addChild(Tag $tag): void
    {
        if (!is_array($this->value)) {
            $this->value = [];
        }
        if (!is_array(@$this->value[$tag->getName()])) {
            $this->value[$tag->getName()] = [];
        }
        $this->value[$tag->getName()][] = $tag;
    }

    public function getChildren(string $tagName): array
    {
        return $this->value[$tagName] ?? [];
    }

    public function getAllChildren(): ?array
    {
        if (is_array($this->value)) {
            $children = array_merge(...array_values($this->value));
            if (isset($this->sortedChildren) && count($this->sortedChildren) > 0) {
                usort($children, function (Tag $a, Tag $b) {
                    $posA = array_search($a->getName(), $this->sortedChildren);
                    $posB = array_search($b->getName(), $this->sortedChildren);
                    $posA = $posA === false ? PHP_INT_MAX : $posA;
                    $posB = $posB === false ? PHP_INT_MAX : $posB;

                    return $posA <=> $posB;
                });
            }

            return $children;
        }

        return null;
    }

    public function setChild(Tag $tag): void
    {
        if (!is_array($this->value)) {
            $this->value = [];
        }
        $this->value[$tag->getName()] = [$tag];
    }

    /**
     * @param array<string, array<Tag>>|string $value
     * @return void
     */
    public function setValue(array|string $value): void
    {
        $this->value = $value;
    }

    public function addAttribute(string $key, string $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function asXML(): string
    {
        $attrs = '';
        foreach ($this->attributes as $key => $value) {
            if (isset($value)) {
                $attrs .= ' ' . $key . '="' . htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
            }
        }

        if (is_null($this->value)) {
            return "<{$this->name}{$attrs}/>\n";
        }

        if (is_array($this->value)) {
            $childrenXML = '';
            foreach ($this->getAllChildren() as $tag) {
                if ($tag instanceof Tag) {
                    $childrenXML .= $tag->asXML();
                }
            }

            return "<{$this->name}{$attrs}>\n{$childrenXML}</{$this->name}>\n";
        }

        $escapedValue = htmlspecialchars($this->value, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return "<{$this->name}{$attrs}>{$escapedValue}</{$this->name}>\n";
    }
}
