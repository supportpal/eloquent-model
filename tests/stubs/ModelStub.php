<?php

use Jenssegers\Model\Model;

class ModelStub extends Model
{
    /** @var string[] */
    protected array $hidden = ['password'];

    /** @var array<string, string> */
    protected array $casts = [
        'age'   => 'integer',
        'score' => 'float',
        'data'  => 'array',
        'active' => 'bool',
        'secret' => 'string',
        'count' => 'int',
        'object_data' => 'object',
        'collection_data' => 'collection',
        'foo' => 'bar',
    ];

    /** @var string[] */
    protected array $guarded = [
        'secret',
    ];

    /** @var string[] */
    protected array $fillable = [
        'name',
        'city',
        'age',
        'score',
        'data',
        'active',
        'count',
        'object_data',
        'default',
        'collection_data',
    ];

    public function getListItemsAttribute(mixed $value): mixed
    {
        return json_decode($value, true);
    }

    public function setListItemsAttribute(mixed $value): void
    {
        $this->attributes['list_items'] = json_encode($value);
    }

    public function setBirthdayAttribute(mixed $value): void
    {
        $this->attributes['birthday'] = strtotime($value);
    }

    public function getBirthdayAttribute(mixed $value): string
    {
        return date('Y-m-d', $value);
    }

    public function getAgeAttribute(mixed $value): int
    {
        $date = DateTime::createFromFormat('U', $this->attributes['birthday']);

        if ($date === false) {
            return 0;
        }

        return $date->diff(new DateTime('now'))->y;
    }

    public function getTestAttribute(mixed $value): string
    {
        return 'test';
    }
}
