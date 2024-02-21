<?php

declare(strict_types=1);

namespace Terminal42\NotificationCenterBundle\Token;

final class Token
{
    public function __construct(private string $tokenName, private mixed $tokenValue, private string $parserValue)
    {
    }

    public function getName(): string
    {
        return $this->tokenName;
    }

    public function getValue(): mixed
    {
        return $this->tokenValue;
    }

    public function getParserValue(): string
    {
        return $this->parserValue;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'value' => $this->getValue(),
            'parserValue' => $this->getParserValue(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['value'],
            $data['parserValue'],
        );
    }

    public static function fromValue(string $tokenName, mixed $tokenValue): self
    {
        return match (true) {
            \is_scalar($tokenValue), $tokenValue instanceof \Stringable => new self($tokenName, $tokenValue, (string) $tokenValue),
            \is_array($tokenValue) => new self($tokenName, $tokenValue, self::flattenArray($tokenValue)),
            default => new self($tokenName, $tokenValue, ''),
        };
    }

    private static function flattenArray(array $array): string
    {
        $chunks = [];

        foreach ($array as $k => $v) {
            if (!\is_string($v)) {
                $chunks[$k] = $k.' ['.json_encode($v).']';
            } else {
                $chunks[$k] = $v;
            }
        }

        if (!array_is_list($chunks)) {
            foreach ($chunks as $k => &$v) {
                $v = $k.': '.$v;
            }
        }

        return implode(', ', $chunks);
    }
}