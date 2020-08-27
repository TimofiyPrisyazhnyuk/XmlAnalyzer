<?php

declare(strict_types=1);

namespace app\services\productMatching\xmlAnalyzer;

use app\exceptions\FileException;

/**
 * Class XmlStructureAnalyzer
 *
 * @author Prisyazhnyuk Timofiy
 */
class XmlStructureAnalyzer
{
    public const CONTENTS_AVERAGE = 'contentsAverage';
    public const CONTENTS_COUNT = 'contentsCount';
    public const ATTRIBUTES_NAMES = 'attributeNames';
    public const RELATIVE_XML_PATHS = 'relativeXmlPaths';
    public const COUNT = 'count';
    public const DEPTH = 'depth';
    public const ROOT = 'root';
    public const TAGS = 'tags';
    public const NAME = 'name';
    public const PATH_XML = 'pathXml';
    public const XML_PATHS = 'XmlPaths';
    public const BASE_TAG_PRODUCT = 'product';
    public const ATTRIBUTE_SEPARATOR = '@';
    public const BASE_LENGTH = 3000;

    /**
     * @var string
     */
    protected string $filePath;

    /**
     * XmlStructureAnalyzer constructor.
     *
     * @param string $filePath
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Analyze xml file.
     *
     * @return array
     * @throws FileException
     */
    public function analyze(): array
    {
        try {
            $fileResource = @fopen($this->filePath, 'rb');
            if ($fileResource === false) {
                throw new FileException('Unable to open file ' . $this->filePath);
            }
            $analyser = new BaseAnalyzer();
            $xmlParser = xml_parser_create();
            xml_parser_set_option($xmlParser, XML_OPTION_CASE_FOLDING, 0);
            xml_set_element_handler($xmlParser, [$analyser, 'startElement'], [$analyser, 'endElement']);
            if (($data = fread($fileResource, static::BASE_LENGTH)) && !xml_parse($xmlParser, $data)) {
                throw new FileException('XML Error: ' . xml_error_string(xml_get_error_code($xmlParser)) . ' at line ' . xml_get_current_line_number($xmlParser));
            }

            return $this->getAnalyzeResult($analyser->getTags());
        } finally {
            @fclose($fileResource);
        }
    }

    /**
     * Get result analyze xml tags.
     *
     * @param array $tags
     *
     * @return array
     */
    protected function getAnalyzeResult(array $tags): array
    {
        $result = [];
        $deepest = '';
        $productTag = '';
        $depth = 0;

        foreach ($tags as $tag => $value) {
            if ($value[static::DEPTH] <= $depth) {
                continue;
            }
            $value[static::COUNT] > 0 ?: $value[static::COUNT] = 1;
            $tags[$tag][static::CONTENTS_AVERAGE] = $value[static::CONTENTS_COUNT] / $value[static::COUNT];
            if ($tags[$tag][static::CONTENTS_AVERAGE] > 2) {
                mb_stripos($tag, static::BASE_TAG_PRODUCT) === false ?: $productTag = $tag;
                $depth = $value[static::DEPTH];
                $deepest = $tag;
            } elseif ($depth == 0) {
                $deepest = $tag;
            }
        }
        $result[static::PATH_XML] = !empty($productTag) ? $productTag : $deepest;
        $result[static::TAGS] = $tags;
        $xPaths = array_filter($tags, function ($value, $key) {
            return isset($value[static::CONTENTS_AVERAGE]) && $value[static::CONTENTS_AVERAGE] > 0 ? $key : false;
        }, ARRAY_FILTER_USE_BOTH);
        $result[static::XML_PATHS] = array_keys($xPaths);
        $result[static::RELATIVE_XML_PATHS] = $this->getRelativeXmlPaths($result);

        return $result;
    }

    /**
     * Get xml mapping.
     *
     * @param array $analyzerData
     *
     * @return array
     */
    protected function getRelativeXmlPaths(array $analyzerData): array
    {
        $relativePaths = [];
        if (!empty($analyzerData[static::TAGS]) && !empty($analyzerData[static::PATH_XML])) {
            foreach ($analyzerData[static::TAGS] as $value) {
                if (isset($value[static::ROOT]) && $value[static::ROOT] === $analyzerData[static::PATH_XML]) {
                    $relativePaths[] = $value[static::NAME];
                }
            }
            if (!empty($analyzerData[static::TAGS][$analyzerData[static::PATH_XML]][static::ATTRIBUTES_NAMES])) {
                $attributes = array_map(function ($value) {
                    return static::ATTRIBUTE_SEPARATOR . $value;
                }, $analyzerData[static::TAGS][$analyzerData[static::PATH_XML]][static::ATTRIBUTES_NAMES]);
                $relativePaths = array_merge($attributes, $relativePaths);
            }
        }

        return $relativePaths;
    }
}
