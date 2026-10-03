<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Category;

/**
 * Validation du formulaire catégorie.
 */
class CategoryValidator extends Validator
{
    /**
     * @param array<string, mixed> $data
     * @param int|null             $id   Catégorie en cours d'édition
     */
    public function __construct(array $data = [], private ?int $id = null)
    {
        parent::__construct($data);
    }

    public function validate(): void
    {
        $name = $this->string('name', __('common.name'));

        $this->required('name', $name);
        $this->maxLength('name', $name, 180);
        $this->maxLength('name_en', 'Nom EN', 180);

        $this->integer('parent_id', __('admin.categories') . ' parent');
        $this->integer('sort_order', __('common.sort_by'));

        $this->inList('status', __('common.status'), [
            Category::STATUS_ACTIVE,
            Category::STATUS_INACTIVE,
        ]);

        $slug = $this->string('slug', 'Slug');

        if ($slug !== '') {
            $this->maxLength('slug', 'Slug', 200);
            $this->slugUnique($slug, 'categories', $this->id);
        }
    }

    /**
     * Données à persister.
     *
     * Les champs absents du formulaire ne sont pas renvoyés : l'édition en
     * ligne ne soumet ni name_en ni description, et les écraser par NULL
     * perdrait des données. Un formulaire complet les soumettant, ils sont
     * alors bien modifiés — y compris pour les vider.
     *
     * @return array<string, string|int|null>
     */
    public function categoryData(): array
    {
        $parentId = $this->value('parent_id');

        $data = [
            'name'       => $this->string('name', 'name'),
            'slug'       => $this->string('slug', 'slug'),
            'parent_id'  => ($parentId !== null && (string) $parentId !== '' && (int) $parentId > 0)
                ? (int) $parentId
                : null,
            'status'     => $this->stringOr('status', Category::STATUS_ACTIVE),
            'sort_order' => max(0, (int) $this->value('sort_order')),
        ];

        if (array_key_exists('name_en', $this->data)) {
            $data['name_en'] = $this->nullable('name_en');
        }

        if (array_key_exists('description', $this->data)) {
            $data['description'] = $this->nullable('description');
        }

        return $data;
    }
}