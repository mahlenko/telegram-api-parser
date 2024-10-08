<?php

namespace TelegramApiParser\ParserDocumentation;

use DateTimeImmutable;
use DiDom\Document;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;

class TelegramDocumentationParser
{
    const BASE_URL = 'https://core.telegram.org/bots/api';

    private ?Document $document = null;

    /**
     * @return string
     * @throws InvalidSelectorException
     */
    public function version(): string {
        $version_el = $this->getContent()
            ->first('h4')
            ->nextSibling('p');

        return preg_replace('/[^\d.]/', '', $version_el->text());
    }

    /**
     * @return DateTimeImmutable
     * @throws InvalidSelectorException
     * @throws \DateMalformedStringException
     */
    public function latestDate(): DateTimeImmutable {
        $date = $this->getContent()
            ->first('h4')
            ->text();

        return new DateTimeImmutable($date);
    }

    /**
     * @return array
     * @throws InvalidSelectorException
     */
    public function handle(): array {
        $heading = $this->getSeparateSections($this->getContent(), 'h3');

        $result = [];
        foreach ($heading as $group) {
            $groupResult = [
                'name' => $group->first('h3')->text(),
                'description' => $this->findGroupDescription($group),
            ];

            // Types, Methods
            $sections = $this->getSeparateSections($group, 'h4');

            foreach ($sections as $section) {
                $table = $this->getParametersFromTable($section);

                if ($table) {
                    $data = [
                        'name' => $section->first('h4')->text(),
                        'description' => $this->findGroupDescription($section),
                        'parameters' => count($table[0]) === 3
                            ? $this->makeObjectParameters($table)
                            : $this->makeMethodParameters($table),
                    ];
                } else {
                    $data = [
                        'name' => $section->first('h4')->text(),
                        'description' => $this->findGroupDescription($section),
                        'response' => null,
                    ];
                }

                /* Is method? */
                $data['return'] = $this->defineReturnType($data['description']);

                $groupResult['sections'][] = array_filter($data);
            }

            $result[] = $groupResult;
        }

        return $result;
    }

    /**
     * @param  Element  $element
     * @param  string  $tag_separator
     * @return array<Element>
     * @throws InvalidSelectorException
     */
    private function getSeparateSections(Element $element, string $tag_separator): array {
        $sections = [];

        $tags = $element->find($tag_separator);

        foreach ($tags as $tag) {
            if (!$element->isElementNode()) continue;

            $nextBlockTitle = next($tags);

            $section = new Element('section');
            $section->appendChild($tag);

            foreach ($tag->nextSiblings() as $sibling) {
                if (!$sibling->isElementNode()) continue;
                if ($nextBlockTitle && $sibling->tagName() == $nextBlockTitle->tagName()) break;
                $section->appendChild($sibling);
            }

            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * @param  Element  $group
     * @return string|null
     */
    private function findGroupDescription(Element $group): ?string {
        $descriptions = [];

        $headers = ['h3' => 'h4', 'h4' => 'table'];
        $firstTag = $group->firstChild()->tagName();
        $stopTag = $headers[$firstTag];

        foreach ($group->children() as $index => $child) {
            if (!$index) continue;
            if ($child->tagName() == $stopTag) break;

            $descriptions[] = $this->chunkDescriptionFormat($child);
        }

        return implode(PHP_EOL, $descriptions);
    }

    /**
     * @param  Element  $element
     * @return string
     */
    private function chunkDescriptionFormat(Element $element): string {
        $value = match($element->tagName()) {
            'blockquote', 'pre' => trim($element->innerHtml()),
            'ul', 'ol' => $this->listElements($element),
            default => trim($element->html())
        };

        return strip_tags($value, ['a', 'em']);
    }

    private function listElements(Element $element): string {
        $items = [];

        foreach ($element->find('li') as $item) {
            $items[] = ' - '. $item->innerHtml();
        }

        return implode(PHP_EOL, $items);
    }

    /**
     * @param  Element  $section
     * @return array
     * @throws InvalidSelectorException
     */
    public function getParametersFromTable(Element $section): array {
        $table = $section->first('table');
        if (!$table) return [];

        $data = [];
        foreach ($table->find('tr') as $tr) {
            $param = [];
            foreach ($tr->find('td') as $td) {
                $param[] = $td->text();
            }

            if ($param) $data[] = $param;
        }

        return $data;
    }

    /**
     * @param  array  $table
     * @return array
     */
    private function makeObjectParameters(array $table): array {
        $parameters = [];

        $optionalKey = 'Optional.';
        foreach ($table as $row) {
            $parameters[] = [
                'name' => $row[0],
                'type' => $this->defineType($row[1]),
                'description' => $this->cleanFormatDescription(str_replace($optionalKey, '', $row[2])),
                'required' => !str_contains($row[2], $optionalKey),
            ];
        }

        return $parameters;
    }

    /**
     * @param  array  $table
     * @return array
     */
    private function makeMethodParameters(array $table): array {
        $parameters = [];

        foreach ($table as $row) {
            $parameters[] = [
                'name' => $row[0],
                'type' => $this->defineType($row[1]),
                'description' => $this->cleanFormatDescription($row[3]),
                'required' => $row[2] !== 'Optional',
            ];
        }

        return $parameters;
    }

    /**
     * @param  string  $text
     * @return string
     */
    private function cleanFormatDescription(string $text): string {
        $text = str_replace(["\u{201c}", "\u{201d}", "\u{00bb}"], ['"', '"', ''], $text);
        return trim($text);
    }

    /**
     * @param  string  $type
     * @return string|array|null
     */
    private function defineType(string $type): string|array|null {
        $arrayKey = 'Array of';
        if (str_contains($type, $arrayKey))
            return $this->formatArrayType($type, [], $arrayKey);

        if (str_contains($type, ' or '))
            return explode(' or ', $type);

        if (str_contains($type, ' '))
            return null;

        return $type;
    }

    /**
     * @param  string  $description
     * @return string|array|null
     * @throws InvalidSelectorException
     */
    private function defineReturnType(string $description): string|array|null {
        if (!str_contains(strtolower($description), 'return'))
            return null;

        $sentences = array_values(array_filter(
            explode('.', $description),
            fn($sentences) => str_contains(strtolower($sentences), 'return')
        ));

        if (!$sentences)
            return null;

        foreach ($sentences as $sentence) {
            $sentence = new Document($sentence);
            $text = $sentence->text();

            if (str_contains(strtolower($text), 'array of')) {
                preg_match('/Array of (.*)/i', $text, $matches);
                if (isset($matches[1])) {
                    $type = explode(' ', $matches[1]);
                    return [ $type[0] ];
                }
            }

            $returnTypeFromEm = $sentence->find('em');
            if ($returnTypeFromEm && count($returnTypeFromEm))
                return $returnTypeFromEm[count($returnTypeFromEm) - 1]->text();

            if ($sentence->has('a'))
                return $sentence->first('a')->text();
        }

        return null;
    }

    /**
     * @param  string  $source
     * @param  array  $build
     * @param  string  $define
     * @return array
     */
    public function formatArrayType(string $source, array $build, string $define = 'Array of'): array {
        $source = substr($source, strlen($define) + 1);

        if (str_contains($source, $define)) {
            $build[] = $this->formatArrayType($source, $build, $define);
            return $build;
        }

        $source = str_replace(' and ', ', ', $source);
        return explode(', ', $source);
    }

    /**
     * @return Document
     */
    private function getDocument(): Document {
        if ($this->document instanceof Document) return $this->document;

        $this->document = new Document(self::BASE_URL, true);
        return $this->document;
    }

    /**
     * @throws InvalidSelectorException
     */
    private function getContent(): Element {
        return $this->getDocument()->first('#dev_page_content');
    }
}