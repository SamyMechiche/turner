<?php

namespace App\Form;

use App\Entity\Book;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class BookFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'attr' => ['placeholder' => 'Enter book title'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a book title',
                    ]),
                ],
            ])
            ->add('author', TextType::class, [
                'label' => 'Author',
                'required' => false,
                'attr' => ['placeholder' => 'Enter author name'],
            ])
            ->add('editor', TextType::class, [
                'label' => 'Publisher/Editor',
                'required' => false,
                'attr' => ['placeholder' => 'Enter publisher or editor'],
            ])
            ->add('total_pages', IntegerType::class, [
                'label' => 'Total Pages',
                'attr' => ['placeholder' => 'Enter total number of pages'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter the total number of pages',
                    ]),
                    new Positive([
                        'message' => 'Total pages must be a positive number',
                    ]),
                ],
            ])
            ->add('cover_image', UrlType::class, [
                'label' => 'Cover Image URL',
                'required' => false,
                'attr' => ['placeholder' => 'Enter cover image URL (optional)'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Book::class,
        ]);
    }
}

