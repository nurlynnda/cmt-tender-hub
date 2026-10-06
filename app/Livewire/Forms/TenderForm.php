<?php

namespace App\Livewire\Forms;

use App\Enums\{TenderCategory, TenderMode, TenderType};
use App\Models\Tender;
use App\Rules\MoneyAmount;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Form;

class TenderForm extends Form
{
    public string $mode = 'EP';
    public string $type = 'TENDER';
    public string $category = 'General';
    public string $tenderCode = '';
    public string $title = '';
    public string $client = '';
    public string $scope = '';
    public string $picId = '';
    public string $ownerId = '';
    public string $publishDate = '';
    public string $closingDate = '';
    public bool $hasBriefing = false;
    public string $briefingDate = '';
    public string $estimatedValue = '';

    public function rules(): array
    {
        $activeUser = Rule::exists('users', 'id')->where('is_active', true);

        return [
            'mode' => ['required', Rule::enum(TenderMode::class)],
            'type' => ['required', Rule::enum(TenderType::class)],
            'category' => ['required', Rule::enum(TenderCategory::class)],
            'tenderCode' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:2000'],
            'client' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'picId' => ['required', $activeUser],
            'ownerId' => ['nullable', $activeUser],
            'publishDate' => ['nullable', 'date_format:Y-m-d'],
            'closingDate' => ['required', 'date_format:Y-m-d', function ($attribute, $value, $fail) {
                if ($this->publishDate !== '' && $value < $this->publishDate) {
                    $fail('The closing date cannot be before the publish date.');
                }
            }],
            'hasBriefing' => ['boolean'],
            'briefingDate' => $this->hasBriefing ? ['required', 'date_format:Y-m-d'] : ['nullable'],
            'estimatedValue' => ['nullable', new MoneyAmount],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'tenderCode' => 'tender code',
            'picId' => 'person in charge',
            'ownerId' => 'opportunity owner',
            'publishDate' => 'publish date',
            'closingDate' => 'closing date',
            'briefingDate' => 'briefing date',
            'estimatedValue' => 'estimated value',
        ];
    }

    public function fillFrom(Tender $t): void
    {
        $this->mode = $t->mode->value;
        $this->type = $t->type->value;
        $this->category = $t->category->value;
        $this->tenderCode = $t->tender_code;
        $this->title = $t->title;
        $this->client = $t->client;
        $this->scope = (string) $t->scope;
        $this->picId = (string) $t->pic_id;
        $this->ownerId = (string) ($t->owner_id ?? '');
        $this->publishDate = (string) $t->publish_date?->toDateString();
        $this->closingDate = $t->closing_date->toDateString();
        $this->hasBriefing = $t->has_briefing;
        $this->briefingDate = (string) $t->briefing_date?->toDateString();
        $this->estimatedValue = Money::toInput($t->estimated_value_sen);
    }

    /** Call only after validate(). */
    public function toData(): array
    {
        $scope = trim($this->scope);

        return [
            'mode' => TenderMode::from($this->mode),
            'type' => TenderType::from($this->type),
            'category' => TenderCategory::from($this->category),
            'tender_code' => trim($this->tenderCode),
            'title' => trim($this->title),
            'client' => trim($this->client),
            'scope' => $scope === '' ? null : $scope,
            'pic_id' => (int) $this->picId,
            'owner_id' => $this->ownerId === '' ? null : (int) $this->ownerId,
            'publish_date' => $this->publishDate === '' ? null : $this->publishDate,
            'closing_date' => $this->closingDate,
            'has_briefing' => $this->hasBriefing,
            'briefing_date' => $this->hasBriefing ? $this->briefingDate : null,
            'estimated_value_sen' => Money::parse($this->estimatedValue),
        ];
    }
}
