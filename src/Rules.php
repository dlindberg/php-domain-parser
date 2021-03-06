<?php
/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */
declare(strict_types=1);

namespace Pdp;

/**
 * A class to resolve domain name against the Public Suffix list
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Rules
{
    const ALL_DOMAINS = 'ALL_DOMAINS';
    const ICANN_DOMAINS = 'ICANN_DOMAINS';
    const PRIVATE_DOMAINS = 'PRIVATE_DOMAINS';

    /**
     * PSL rules as a multidimentional associative array
     *
     * @var array
     */
    private $rules;

    /**
     * Returns a new instance from a file path.
     *
     * @param string        $path
     * @param null|resource $context
     *
     * @return self
     */
    public static function createFromPath(string $path, $context = null): self
    {
        $args = [$path, 'r', false];
        if (null !== $context) {
            $args[] = $context;
        }

        if (!($resource = @fopen(...$args))) {
            throw new Exception(sprintf('`%s`: failed to open stream: No such file or directory', $path));
        }

        $content = stream_get_contents($resource);
        fclose($resource);

        return self::createFromString($content);
    }

    /**
     * Returns a new instance from a string.
     *
     * @param string $content
     *
     * @return self
     */
    public static function createFromString(string $content): self
    {
        return new self((new Converter())->convert($content));
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['rules']);
    }

    /**
     * New instance.
     *
     * @param array $rules
     */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Determines the public suffix for a given domain.
     *
     * @param string|null $domain
     * @param string      $section
     *
     * @throws Exception
     *                   If the Domain is invalid or malformed
     *                   If the section is invalid or not supported
     *                   If the PublicSuffix can not be converted using against the domain encoding.
     *
     * @return PublicSuffix
     */
    public function getPublicSuffix(string $domain = null, string $section = self::ALL_DOMAINS): PublicSuffix
    {
        $this->validateSection($section);
        if (!$this->isMatchable($domainObj = new Domain($domain))) {
            throw new Exception(sprintf('The domain `%s` can not contain a public suffix', $domain));
        }

        return $this->findPublicSuffix($domainObj, $section);
    }

    /**
     * Returns PSL info for a given domain.
     *
     * @param string|null $domain
     * @param string      $section
     *
     * @return Domain
     */
    public function resolve(string $domain = null, string $section = self::ALL_DOMAINS): Domain
    {
        $this->validateSection($section);
        try {
            $domain = new Domain($domain);
            if (!$this->isMatchable($domain)) {
                return $domain;
            }

            return new Domain($domain->getContent(), $this->findPublicSuffix($domain, $section));
        } catch (Exception $e) {
            return new Domain();
        }
    }

    /**
     * Assert the section status.
     *
     * @param string $section
     *
     * @throws Exception if the submitted section is not supported
     */
    private function validateSection(string $section)
    {
        if (self::ALL_DOMAINS === $section) {
            return;
        }

        $rules = $this->rules[$section] ?? null;
        if (null !== $rules && is_array($rules)) {
            return;
        }

        throw new Exception(sprintf('%s is an unknown Public Suffix List section', $section));
    }

    /**
     * Tells whether the given domain can be resolved.
     *
     * @param DomainInterface $domain
     *
     * @return bool
     */
    private function isMatchable(DomainInterface $domain): bool
    {
        return 1 < count($domain)
            && 0 < strpos($domain->getContent(), '.');
    }

    /**
     * Returns the matched public suffix.
     *
     * @param DomainInterface $domain
     * @param string          $section
     *
     * @return PublicSuffix
     */
    private function findPublicSuffix(DomainInterface $domain, string $section): PublicSuffix
    {
        $asciiDomain = $domain->toAscii();
        $icann = $this->findPublicSuffixFromSection($asciiDomain, self::ICANN_DOMAINS);
        if (self::ICANN_DOMAINS === $section) {
            return $this->normalizePublicSuffix($icann, $domain);
        }

        $private = $this->findPublicSuffixFromSection($asciiDomain, self::PRIVATE_DOMAINS);
        if (count($private) > count($icann)) {
            return $this->normalizePublicSuffix($private, $domain);
        }

        if (self::ALL_DOMAINS === $section) {
            return $this->normalizePublicSuffix($icann, $domain);
        }

        return $this->normalizePublicSuffix(new PublicSuffix(), $domain);
    }

    /**
     * Returns the public suffix matched against a given PSL section.
     *
     * @param DomainInterface $domain
     * @param string          $section
     *
     * @return PublicSuffix
     */
    private function findPublicSuffixFromSection(DomainInterface $domain, string $section): PublicSuffix
    {
        $rules = $this->rules[$section] ?? [];
        $matches = [];
        foreach ($domain as $label) {
            //match exception rule
            if (isset($rules[$label], $rules[$label]['!'])) {
                break;
            }

            //match wildcard rule
            if (isset($rules['*'])) {
                $matches[] = $label;
                break;
            }

            //no match found
            if (!isset($rules[$label])) {
                break;
            }

            $matches[] = $label;
            $rules = $rules[$label];
        }

        if (empty($matches)) {
            return new PublicSuffix();
        }

        return new PublicSuffix(implode('.', array_reverse($matches)), $section);
    }

    /**
     * Normalize the found Public Suffix against its domain name.
     *
     * @param PublicSuffix    $publicSuffix
     * @param DomainInterface $domain
     *
     * @return PublicSuffix
     */
    private function normalizePublicSuffix(PublicSuffix $publicSuffix, DomainInterface $domain): PublicSuffix
    {
        if (null === $publicSuffix->getContent()) {
            $publicSuffix = new PublicSuffix($domain->getLabel(0));
        }

        if (false === strpos($domain->getContent() ?? '', 'xn--')) {
            return $publicSuffix->toUnicode();
        }

        return $publicSuffix;
    }
}
