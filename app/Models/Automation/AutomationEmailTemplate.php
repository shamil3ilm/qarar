<?php

declare(strict_types=1);

namespace App\Models\Automation;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class AutomationEmailTemplate extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%");
        });
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Render the template by replacing variables with provided data.
     */
    public function render(array $data): array
    {
        $subject = $this->replaceVariables($this->subject, $data);
        $bodyHtml = $this->replaceVariables($this->body_html, $data);
        $bodyText = $this->body_text
            ? $this->replaceVariables($this->body_text, $data)
            : strip_tags($bodyHtml);

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Replace {{variable}} placeholders with actual values.
     */
    protected function replaceVariables(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $content = str_replace('{{' . $key . '}}', (string) ($value ?? ''), $content);
            }
        }

        // Remove any unreplaced variables
        $content = preg_replace('/\{\{[^}]+\}\}/', '', $content);

        return $content;
    }

    /**
     * Get available variable names for this template.
     */
    public function getAvailableVariables(): array
    {
        return $this->variables ?? [];
    }
}
