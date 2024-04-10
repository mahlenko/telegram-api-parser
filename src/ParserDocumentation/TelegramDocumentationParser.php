<?php

namespace TelegramApiParser\ParserDocumentation;

use DateTimeImmutable;
use DiDom\Document;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;

class TelegramDocumentationParser
{
    const BASE_URL = 'https://core.telegram.org/bots/api';

    const BASE_TYPES = ['int', 'integer', 'float', 'double', 'string', 'bool', 'boolean', 'true', 'false'];

    public function version(): float {
        $document = new Document(self::BASE_URL, true);
        $version_el = $document
            ->first('#dev_page_content')
            ->first('h4')
            ->nextSibling('p');

        return floatval(preg_replace('/[^0-9.]/', '', $version_el->text()));
    }

    public function latestDate(): DateTimeImmutable {
        $document = new Document(self::BASE_URL, true);
        $date = $document
            ->first('#dev_page_content')
            ->first('h4')
            ->text();

        return new DateTimeImmutable($date);
    }

    /**
     * @throws InvalidSelectorException
     */
    public function handle(): array {
        $document = new Document(self::BASE_URL, true);

        $groups = $this->chunkDocumentToSections(
            $document->first('#dev_page_content'),
            'h3');

        $result = [];
        foreach ($groups as $group) {
            $groupResult = [
                'name' => $this->findGroupName($group, 'h3'),
                'description' => $this->findGroupDescription($group),
                'sections' => []
            ];

            // Types, Methods
            $sections = $this->chunkDocumentToSections($group, 'h4');
            foreach ($sections as $section) {
                $params = $this->parseTableData($section);
                if ($params) {
                    foreach ($params as $index => $param) {
                        $params[$index] = $this->convertParams($param);
                    }
                }

                $groupResult['sections'][] = [
                    'name' => $this->findGroupName($section, 'h4'),
                    'description' => $this->findDescriptionSection($section),
                    'response' => $this->findResponseSection($section),
                    'params' => $params
                ];
            }

            $result[] = $groupResult;
        }

        return $result;
    }

    /**
     * Вернет блоки DOMElement разделенные на группы:
     *
     *  - Recent changes
     *  - Authorizing your bot
     *  - Making requests
     *  - Using a Local Bot API Server
     *  - Getting updates
     *  - Available types
     *  - Available methods
     *  - Updating messages
     *  - Stickers
     *  - Inline mode
     *  - Payments
     *  - Telegram Passport
     *  - Games
     *
     * @throws InvalidSelectorException
     * @return array<Document>
     */
    private function findNavigationBlocks(Document $document): array {
        $content = $document->first('#dev_page_content');

        $documents = [];
        $block_titles = $content->find('h3');

        foreach ($block_titles as $element) {
            if (!$element->isElementNode()) continue;

            $nextBlockTitle = next($block_titles);

            $document = new Document();
            $document->appendChild($element);

            foreach ($element->nextSiblings() as $sibling) {
                if (!$sibling->isElementNode()) continue;
                if ($nextBlockTitle && $sibling->tagName() == $nextBlockTitle->tagName()) break;
                $document->appendChild($sibling);
            }

            $documents[] = $document;
        }

        return $documents;
    }

    /**
     * Разбивает на секции:
     *  - заголовок
     *  - описание
     *  - таблица с данными
     * @param Element $element
     * @param string $tag_separator
     * @return array
     * @throws InvalidSelectorException
     */
    private function chunkDocumentToSections(Element $element, string $tag_separator) {
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
     * Название группы
     *
     * @param Element $element
     * @return string|null
     * @throws InvalidSelectorException
     */
    private function findGroupName(Element $element, string $tag): ?string {
        return $element->first($tag)->text();
    }

    /**
     * Описание группы
     *
     * @param Element $element
     * @return string|null
     * @throws InvalidSelectorException
     */
    private function findGroupDescription(Element $element): ?string {
        $descriptions = [];

        foreach ($element->children() as $child) {
            if ($child->tagName() == 'h3') continue;
            if ($child->tagName() == 'h4') break;

            // достаем описание из параграфов
            $descriptions[] = $this->chunkDescriptionFormat($child);
        }

        return trim(implode(PHP_EOL, $descriptions));
    }

    private function chunkDescriptionFormat(Element $element): string {
        switch ($element->tagName()) {
            case 'pre': $result = '`'. $element->text() .'`'; break;
            case 'ul':
            case 'ol':
                $items = [];
                foreach ($element->find('li') as $li) {
                    $items[] = ' - '. $li->text();
                }
                $result = implode(PHP_EOL, $items);
                break;
            default: $result = $element->text(); break;
        }

        return $result;
    }

    private function findDescriptionSection(Element $element): ?string {
        $descriptions = [];

        foreach ($element->children() as $child) {
            if ($child->tagName() == 'table') break;
            if (in_array($child->tagName(), ['h1', 'h2', 'h3', 'h4'])) continue;
            $descriptions[] = $this->chunkDescriptionFormat($child);
        }

        return implode(PHP_EOL, $descriptions);
    }

    private function findResponseSection(Element $element): ?array {
        $descriptions = [];

        foreach ($element->children() as $child) {
            if ($child->tagName() == 'table') break;
            if (in_array($child->tagName(), ['h3', 'h4'])) continue;
            $descriptions[] = $child->innerHtml();
        }

        $description = implode(' ', $descriptions);

        if (str_contains(strtolower($description), 'return')) {
            // поиск предложений где встречается слово "return"
            $word_lines = array_values(
                array_filter(
                    explode('. ', $description),
                    fn($line) => str_contains(strtolower($line), 'return')
                )
            );

            $types = [];

            // поиск возвращаемых значений
            foreach ($word_lines as $line) {
                $type = null;
                $document = new Document($line);

                $typeFromLink = $document->first('a[href^=#]');
                if ($typeFromLink) $type = $typeFromLink->text();

                $em = $document->first('em');
                if ($em) {
                    $type = in_array(strtolower($em->text()), self::BASE_TYPES)
                        ? $em->text()
                        : null;
                }

                if (isset($type)) {
                    if (str_contains($type, ' ')) {
                        $words = explode(' ', $type);
                        foreach ($words as $index => $word) {
                            $words[$index] = ucfirst(trim($word));
                        }
                        $type = implode('', $words);
                    }

                    if ($type == 'Messages') $type = 'Message';

                    if (str_contains(strtolower($line), 'objects') || str_contains(strtolower($line), 'array of')) {
                        $type = 'Array of '.  $type;
                    }

                    if (strncmp(ucfirst($type), $type, 1) === 0) {
                        $types[] = $this->typeConvert($type);
                    }
                }
            }

        }

        return $types ?? null;
    }

    private function convertParams(array $param): array {
        if (count($param) === 3) {
            $name = trim($param[0]);
            $type = $this->typeConvert($param[1]);
            $optional = $this->isOptional($param[2]);
            $description = $optional
                ? trim(str_replace('Optional.', '', $param[2]))
                : $param[2];

            return [
                'name' => $name,
                'type' => $type,
                'description' => $description,
                'optional' => $optional
            ];
        }

        $type = $this->typeConvert($param[1]);

        $required = match ($param[2]) {
            'Yes' => true,
            'Optional' => false
        };

        return [
            'name' => $param[0],
            'type' => $type,
            'required' => $required,
            'description' => $param[3],
        ];
    }

    private function typeConvert(string $type): string|array {
        $isArray = false;
        if (str_contains($type, ' of ')) {
            $isArray = true;
            $_temp = explode(' of ', $type);
            $type = $_temp[array_key_last($_temp)];
        }

        if (str_contains($type, ' or ')) {
            $types = explode(' or ', $type);
        } else {
            $types = [ $type ];
        }

        if (str_contains($type, ' and ')) {
            $type = str_replace(' and ', ', ', $type);
            $types = explode(', ', $type);
        }

        foreach ($types as $index => $str) {
            if (in_array(strtolower($str), self::BASE_TYPES)) {
                $str = match (strtolower($str)) {
                    'int', 'integer' => 'int',
                    'float', 'double' => 'float',
                    'string' => 'string',
                    'true' => 'true',
                    'false' => 'false',
                    'bool', 'boolean' => 'bool',
                };
            }

            $types[$index] = $str;
        }

        if ($isArray) {
            foreach ($types as $index => $str) {
                $types[$index] = $str .'[]';
            }
        }

        return count($types) == 1 ? $types[0] : $types;
    }

    private function isOptional(string $description): bool {
        return str_contains($description, 'Optional.');
    }

    /**
     * @throws InvalidSelectorException
     */
    public function parseTableData(Element $element): array {
        $table = $element->first('table');
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
}