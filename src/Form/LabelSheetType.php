<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\LabelSystem\LabelPageSize;
use App\Entity\LabelSystem\LabelSheet;
use App\Entity\LabelSystem\LabelUnit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LabelSheetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'name.label',
            'empty_data' => '',
        ]);
        $builder->add('page_size', EnumType::class, [
            'label' => 'label_options.page_size_name.label', 'class' => LabelPageSize::class,
            'choice_label' => static fn(LabelPageSize $size) => $size->value,
            'attr' => ['data-pages--label-sheet-target' => 'pageSize', 'data-action' => 'change->pages--label-sheet#applyPreset'],
        ]);
        $builder->add('unit', EnumType::class, [
            'label' => 'label_options.unit.label', 'class' => LabelUnit::class,
            'choice_label' => static fn(LabelUnit $unit) => match ($unit) {
                LabelUnit::MILLIMETER => 'label_options.unit.mm', LabelUnit::INCH => 'label_options.unit.inch',
            },
            'attr' => ['data-pages--label-sheet-target' => 'unit', 'data-action' => 'change->pages--label-sheet#applyPreset'],
        ]);
        foreach ([
            'width' => 'label_options.sheet_width.label', 'height' => 'label_options.sheet_height.label',
            'label_width' => false, 'label_height' => false,
            'margin_left' => 'label_options.sheet_margin_left.label', 'margin_top' => 'label_options.sheet_margin_top.label',
            'gutter_width' => 'label_options.sheet_gutter_width.label', 'gutter_height' => 'label_options.sheet_gutter_height.label',
            'columns' => 'label_options.sheet_columns.label', 'rows' => 'label_options.sheet_rows.label',
            'corner_radius' => 'label_options.sticker_corner_radius.label',
        ] as $name => $label) {
            $integer = in_array($name, ['columns', 'rows'], true);
            $allowsZero = str_contains($name, 'margin') || str_contains($name, 'gutter') || str_contains($name, 'radius');
            $attr = ['min' => $allowsZero ? 0 : 1, 'step' => $integer ? 1 : 'any'];
            if (!$integer) {
                $attr['data-pages--label-sheet-target'] = in_array($name, ['width', 'height'], true)
                    ? 'dimension '.$name
                    : 'dimension';
            }
            $fieldOptions = ['label' => $label, 'attr' => $attr];
            if (!$integer) {
                $fieldOptions['html5'] = true;
            }
            $builder->add($name, $integer ? IntegerType::class : NumberType::class, $fieldOptions);
        }
        $builder->add('save', SubmitType::class, [
            'label' => $options['data']->getID() === null ? 'entity.create' : 'entity.edit.save',
            'attr' => ['class' => $options['data']->getID() === null ? 'btn-success' : 'btn-primary'],
        ]);
        $builder->add('reset', ResetType::class, ['label' => 'entity.edit.reset']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', LabelSheet::class);
    }
}
