<?php

namespace App\Form;

use App\Entity\Events;
use Doctrine\DBAL\Types\DateTimeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
class EventsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('Discription')
            ->add('deadline', DateType::class, [
                'widget' => 'single_text'])
            ->add('DateStart', DateType::class, [
                'widget' => 'single_text'])
            ->add('DateEnd', DateType::class, [
                'widget' => 'single_text'])
            ->add('Link')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Events::class,
        ]);
    }
}
