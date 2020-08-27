<?php

declare(strict_types=1);

namespace app\services\productMatching\xmlAnalyzer;

use app\exceptions\FileException;

/**
 * Class BaseAnalyzer
 *
 * @author Prisyazhnyuk Timofiy
 */
class BaseAnalyzer
{
    protected const BASE_LIMIT = 1000;

    /**
     * @var array
     */
    private array $_stack;

    /**
     * @var array
     */
    private array $_tags = [];

    /**
     * @var null|int
     */
    private ?int $_limit = null;

    /**
     * BaseAnalyzer constructor.
     *
     * @param int $limit
     */
    public function __construct(int $limit = self::BASE_LIMIT)
    {
        $this->_stack = [];
        $this->_limit = $limit;
    }

    /**
     * Get all xml tags.
     *
     * @return array
     */
    public function getTags(): array
    {
        return $this->_tags;
    }

    /**
     * End xml element.
     *
     * @throws FileException
     */
    public function endElement(): void
    {
        array_pop($this->_stack);
        if ($this->_limit !== null && (--$this->_limit) <= 0) {
            throw new FileException('End element.');
        }
    }

    /**
     * Start xml element.
     *
     * @param resource $parser
     * @param string $name
     * @param array $attrs
     */
    public function startElement($parser, string $name, array $attrs): void
    {
        $root = '';
        if (count($this->_stack) > 0) {
            $root = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $this->_stack);
        }
        $current = $root . DIRECTORY_SEPARATOR . $name;
        $this->_stack[] = $name;
        $depth = count($this->_stack);
        if (count($this->_stack) > 1) {
            if (empty($this->_tags[$root])) {
                $this->_tags[$root] = [XmlStructureAnalyzer::CONTENTS_COUNT => 0];
            }
            $this->_tags[$root][XmlStructureAnalyzer::CONTENTS_COUNT]++;
        }
        if (empty($this->_tags[$current])) {
            $this->_tags[$current] = [
                XmlStructureAnalyzer::ATTRIBUTES_NAMES => array_keys($attrs),
                XmlStructureAnalyzer::COUNT => 1,
                XmlStructureAnalyzer::NAME => $name,
                XmlStructureAnalyzer::CONTENTS_COUNT => 0,
                XmlStructureAnalyzer::ROOT => $root,
                XmlStructureAnalyzer::DEPTH => $depth];
        } else {
            $this->_tags[$current][XmlStructureAnalyzer::COUNT]++;
        }
    }
}
