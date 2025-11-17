<?php

namespace App\Form;

use App\Entity\Note;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NoteFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('page', IntegerType::class, [
                'required' => false,
                'attr' => [
                    'min' => 1,
                    'placeholder' => 'Page number (optional)',
                ],
                'label' => 'Page',
            ])
            ->add('content', TextareaType::class, [
                'required' => true,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Write your thoughts...',
                ],
                'label' => 'Note',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Note::class,
        ]);
    }
}

