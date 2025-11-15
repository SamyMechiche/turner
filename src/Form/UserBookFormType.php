<?php

namespace App\Form;

use App\Entity\UserBook;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\GreaterThan;

class UserBookFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $book = $options['book'];
        $userBook = $options['userBook'];
        $currentPage = $userBook?->getCurrentPage() ?? 0;
        $totalPages = $book->getTotalPages();
        $remainingPages = max(0, $totalPages - $currentPage);

        $builder
            ->add('objective_type', ChoiceType::class, [
                'label' => 'Set Objective',
                'choices' => [
                    'Pages per day' => 'daily_goal',
                    'Finish by date' => 'deadline',
                ],
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
                'data' => $userBook?->getDailyGoal() ? 'daily_goal' : ($userBook?->getDeadline() ? 'deadline' : 'daily_goal'),
                'attr' => ['class' => 'objective-type-radio'],
            ])
            ->add('daily_goal', IntegerType::class, [
                'label' => 'Pages per day',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter pages per day',
                    'min' => 1,
                ],
                'constraints' => [
                    new Positive([
                        'message' => 'Daily goal must be a positive number',
                    ]),
                ],
            ])
            ->add('deadline', DateType::class, [
                'label' => 'Finish by',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'min' => (new \DateTime('today'))->format('Y-m-d'),
                ],
                'constraints' => [
                    new GreaterThan([
                        'value' => new \DateTime('today'),
                        'message' => 'Deadline must be in the future',
                    ]),
                ],
            ])
            ->add('current_page', IntegerType::class, [
                'label' => 'Current Page',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter current page',
                    'min' => 0,
                    'max' => $totalPages,
                ],
                'constraints' => [
                    new Positive([
                        'message' => 'Current page must be a positive number',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserBook::class,
            'book' => null,
            'userBook' => null,
        ]);

        $resolver->setRequired(['book']);
        $resolver->setAllowedTypes('book', 'App\Entity\Book');
        $resolver->setAllowedTypes('userBook', ['App\Entity\UserBook', 'null']);
    }
}

