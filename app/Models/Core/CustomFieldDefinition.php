<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class CustomFieldDefinition extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    // Field types
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_SELECT = 'select';
    public const TYPE_MULTISELECT = 'multiselect';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_FILE = 'file';
    public const TYPE_URL = 'url';
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';

    public const FIELD_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_NUMBER,
        self::TYPE_DECIMAL,
        self::TYPE_DATE,
        self::TYPE_DATETIME,
        self::TYPE_BOOLEAN,
        self::TYPE_SELECT,
        self::TYPE_MULTISELECT,
        self::TYPE_TEXTAREA,
        self::TYPE_FILE,
        self::TYPE_URL,
        self::TYPE_EMAIL,
        self::TYPE_PHONE,
    ];

    // Supported entity types
    public const ENTITY_INVOICE = 'invoice';
    public const ENTITY_CUSTOMER = 'customer';
    public const ENTITY_PRODUCT = 'product';
    public const ENTITY_EMPLOYEE = 'employee';
    public const ENTITY_CONTACT = 'contact';
    public const ENTITY_LEAD = 'lead';
    public const ENTITY_PURCHASE_ORDER = 'purchase_order';
    public const ENTITY_BILL = 'bill';

    public const ENTITY_TYPES = [
        self::ENTITY_INVOICE,
        self::ENTITY_CUSTOMER,
        self::ENTITY_PRODUCT,
        self::ENTITY_EMPLOYEE,
        self::ENTITY_CONTACT,
        self::ENTITY_LEAD,
        self::ENTITY_PURCHASE_ORDER,
        self::ENTITY_BILL,
    ];

    protected $fillable = [
        'organization_id',
        'entity_type',
        'field_name',
        'field_label',
        'field_type',
        'description',
        'options',
        'validation',
        'default_value',
        'placeholder',
        'display_order',
        'field_group',
        'is_required',
        'is_unique',
        'is_searchable',
        'is_filterable',
        'show_in_list',
        'show_in_form',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation' => 'array',
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_searchable' => 'boolean',
            'is_filterable' => 'boolean',
            'show_in_list' => 'boolean',
            'show_in_form' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    // Relationships

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'field_definition_id');
    }

    // Scopes

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearchable($query)
    {
        return $query->where('is_searchable', true);
    }

    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }

    public function scopeVisibleInList($query)
    {
        return $query->where('show_in_list', true);
    }

    public function scopeVisibleInForm($query)
    {
        return $query->where('show_in_form', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('field_label');
    }

    public function scopeInGroup($query, string $groupSlug)
    {
        return $query->where('field_group', $groupSlug);
    }

    // Helpers

    public function hasOptions(): bool
    {
        return in_array($this->field_type, [self::TYPE_SELECT, self::TYPE_MULTISELECT]);
    }

    public function getValueColumn(): string
    {
        return match ($this->field_type) {
            self::TYPE_NUMBER, self::TYPE_DECIMAL => 'value_number',
            self::TYPE_DATE => 'value_date',
            self::TYPE_DATETIME => 'value_datetime',
            self::TYPE_BOOLEAN => 'value_boolean',
            self::TYPE_MULTISELECT, self::TYPE_FILE => 'value_json',
            default => 'value_text',
        };
    }
}
