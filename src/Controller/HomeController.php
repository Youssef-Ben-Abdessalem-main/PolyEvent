<?php

namespace App\Controller;

use App\Entity\Events;
use App\Entity\Participants;
use App\Entity\User;
use App\Form\EventsType;
use App\Repository\EventsRepository;
use App\Repository\ParticipantsRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository;
use DoctrineExtensions\Query\Mysql\Unserialize;

class HomeController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

        /**
     * Check if the current user has joined a specific event.
     *
     * @param int $eventId
     * @return bool
     */
    private function hasUserJoinedEvent(int $eventId): bool
    {
        $user = $this->getUser();
        
        if ($user) {
            $userId = $user->getId();

            // Check if the user has joined the event
            $check = $this->em->createQueryBuilder()
                ->select('e')
                ->from(Participants::class, 'e')
                ->where('e.id_user = :userId')
                ->andWhere('e.id_event = :eventId')
                ->setParameter('userId', $userId)
                ->setParameter('eventId', $eventId);

            $queryCheck = $check->getQuery();
            $result = $queryCheck->getResult();

            return count($result) > 0;
        }

        return false;
    }

    #[Route('/home', name: 'app_home')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $id = $user->getId();
        $firstName = $user->getFirstName();
        $secondName = $user->getSecondName();
        $matricule = $user->getMatricule();

        $events = new Events();
        $events->setIdUser($id);
        $form = $this->createForm(EventsType::class, $events);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($events);
            $entityManager->flush();
            return $this->redirectToRoute('app_home');
        }


        //get current date
        $currentDate = new DateTime();
        $currentDate->setTime(0, 0, 0);

        //Find approved event
        $activated = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Events::class, 'e')
            ->where('e.CheckValid = 1');
        $queryActive = $activated->getQuery();
        $active = $queryActive->getResult();

        //check if current user is joined the event or not
        $check = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Participants::class, 'e')
            ->where('e.id_user = :identify_user')
            ->setParameter('identify_user',$id);
        $query_check = $check->getQuery();
        $check_done = $query_check->getResult();


        $eventIdToCheck = 1; 
        $userJoinedEvent = $this->hasUserJoinedEvent($eventIdToCheck);

        return $this->render('home/index.html.twig', [
            'id'=>$id,
            'firstName' => $firstName,
            'secondName' => $secondName,
            'approved_event'=>$active,
            'Today'=>$currentDate,
            'mat'=>$matricule,
            'check'=>$check_done,
            'form' => $form->createView(),
            'eventIdToCheck' => $eventIdToCheck,
            'userJoinedOrNot'=> $userJoinedEvent
        ]);
    }

    #[Route('/home/addevent', name: 'addevent')]
    public function add_event(Request $request): Response
    {
        $user = $this->getUser();
        $id = $user->getId();
        $events = new Events();
        $events->setIdUser($id);
        $form = $this->createForm(EventsType::class, $events);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($events);
            $entityManager->flush();

            return $this->redirectToRoute('app_home');
        }
        return $this->render('home/Events/add.html.twig', [
            'calendar' => $events,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/home/add_participant', name: 'add_participant')]
    public function add_participant(Request $request,Session $session): Response
    {
        $session->getFlashBag()->add('success', 'Joined');

        $id_event = $request->query->get('id_event');
        $id_user = $request->query->get('id_user');
        $user_matricule = $request->query->get('user_matricule');

        $participant = new Participants();
        $participant->setIdUser($id_user);
        $participant->setIdEvent($id_event);
        $participant->setUserMatricule($user_matricule);

        $entityManager = $this->getDoctrine()->getManager();

        $entityManager->persist($participant);

        $entityManager->flush();

        return $this->redirectToRoute('app_home');
    }

    #[Route('/home/participant/Events/{identidfiant}', name: 'app_home_part_show', methods: 'GET')]
    public function participant_display(UserRepository $userRepository,EventsRepository $eventsRepository,ParticipantsRepository $participantsRepository,int $identidfiant): Response
    {
        $event_number = $identidfiant;
        $user = $this->getUser();
        $id = $user->getId();
        $firstName = $user->getFirstName();
        $secondName = $user->getSecondName();

        //find selected event to display
        $entityManager = $this->getDoctrine()->getManager();

        $item = $entityManager->getRepository(Participants::class)->find($identidfiant);

        //find the user who sugg the event
        $participant = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->innerJoin(Participants::class,'e','WITH','e.id_user = u.id')
            ->andWhere('e.id_event = :identidfiant')
            ->setParameter('identidfiant', $identidfiant)
            ->getQuery();
        $part_result = $participant->getResult();

        return $this->render('home/Participants/show.html.twig', [
            'id'=>$id,
            'firstName' => $firstName,
            'secondName' => $secondName,
            'part_of_event'=>$part_result,
            'event_number'=>$event_number,

        ]);

    }

    #[Route('/home/Event/display/{identidfiant}', name: 'app_home_display', methods: 'GET')]
    public function event_home_displays(UserRepository $userRepository,EventsRepository $eventsRepository,int $identidfiant): Response
    {
        $user = $this->getUser();
        $id = $user->getId();
        $firstName = $user->getFirstName();
        $secondName = $user->getSecondName();
        $currentDate = new DateTime();
        $currentDate->setTime(0, 0, 0);

        //find selected event to display
        $entityManager = $this->getDoctrine()->getManager();

        $item = $entityManager->getRepository(Events::class)->find($identidfiant);


        // render the template and pass the first name to it
        return $this->render('home/Events/display.html.twig', [
            'firstName' => $firstName,
            'secondName' => $secondName,
            'Today'=>$currentDate,
            'id'=>$id,
            'displayitem'=>$item,
        ]);

    }


    #[Route('/remove_event', name: 'delete_event')]
    public function delete_event(Request $request,Session $session): Response
    {
        $session->getFlashBag()->add('danger', 'Event Deleted With Success');

        $id = $request->query->get('id');

        $entityManager = $this->getDoctrine()->getManager();

        $myEntity = $entityManager->getRepository(Events::class)->find($id);

        $entityManager->remove($myEntity);
        $entityManager->flush();

        return $this->redirectToRoute('app_admin_details');
    }

    #[Route('/validate', name: 'validate_event')]
    public function validate(Request $request,Session $session): Response
    {
        $session->getFlashBag()->add('success', 'Event Approved');

        $id = $request->query->get('id');

        $entityManager = $this->getDoctrine()->getManager();

        $myEntity = $entityManager->getRepository(Events::class)->find($id);

        $myEntity->setCheckValid(1);

        $entityManager->flush();

        return $this->redirectToRoute('app_admin_details');
    }

    #[Route('/dis-activate', name: 'changeActivationStat')]
    public function No_activate(Request $request,Session $session): Response
    {
        $id = $request->query->get('id');

        $entityManager = $this->getDoctrine()->getManager();

        $myEntity = $entityManager->getRepository(User::class)->find($id);

        $myEntity->setActive(0);

        $entityManager->flush();

        return $this->redirectToRoute('app_logout');
    }

    #[Route('/put_to_archive', name: 'archive_event')]
    public function arch(Request $request,Session $session): Response
    {
        $session->getFlashBag()->add('danger', 'Event Archived');

        $id = $request->query->get('id');

        $entityManager = $this->getDoctrine()->getManager();

        $myEntity = $entityManager->getRepository(Events::class)->find($id);

        $myEntity->setCheckArchive(1);

        $entityManager->flush();

        return $this->redirectToRoute('app_admin_details');
    }

    #[Route('/out_of_archive', name: 'unarchive_event')]
    public function unarch(Request $request,Session $session): Response
    {
        $session->getFlashBag()->add('success', 'Event Unarchived');

        $id = $request->query->get('id');

        $entityManager = $this->getDoctrine()->getManager();

        $myEntity = $entityManager->getRepository(Events::class)->find($id);

        $myEntity->setCheckArchive(0);

        $entityManager->flush();

        return $this->redirectToRoute('app_admin_details');
    }


    #[Route('/Decline', name: 'decline_event')]
    public function Decline(Request $request,Session $session): Response
    {
        $session->getFlashBag()->add('danger', 'Event Denied');

        $id = $request->query->get('id');

        $entityManager = $this->getDoctrine()->getManager();

        $myEntity = $entityManager->getRepository(Events::class)->find($id);

        $myEntity->setCheckValid(2);

        $entityManager->flush();

        return $this->redirectToRoute('app_admin_details');
    }



    #[Route('/admin', name: 'app_admin')]
    public function admin(UserRepository $userRepository,EventsRepository $eventsRepository,ParticipantsRepository $participantsRepository): Response
    {
        $user = $this->getUser();
        $id = $user->getId();
        $firstName = $user->getFirstName();
        $secondName = $user->getSecondName();

        //find all event
        $events = $eventsRepository->findAll();

        //Find active users
        $Active_users = $this->em->createQueryBuilder()
            ->select('e')
            ->from(User::class, 'e')
            ->where('e.active = 1');

        $query_active_users = $Active_users->getQuery();
        $result_active_users = $query_active_users->getResult();

        //get current date
        $currentDate = new DateTime();
        $currentDate->setTime(0, 0, 0);

        //count Completed events
        $countCE = $this->em->createQueryBuilder();
        $countCE->select($countCE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.DateEnd < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $countCEquery = $countCE->getQuery();
        $RCCE = $countCEquery->getSingleScalarResult();

        //count active events
        $countAE = $this->em->createQueryBuilder();
        $countAE->select($countAE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.DateEnd > :today')
            ->andWhere('e.DateStart < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $countAEquery = $countAE->getQuery();
        $RCAE = $countAEquery->getSingleScalarResult();

        //count all events
        $count = $this->em->createQueryBuilder();
        $count->select($count->expr()->count('e.id'))
            ->from(Events::class, 'e');

        $query = $count->getQuery();
        $result = $query->getSingleScalarResult();

        //count on hold events
        $OHE = $this->em->createQueryBuilder();
        $OHE->select($OHE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.CheckValid = 0');

        $OHEQ = $OHE->getQuery();
        $OHER = $OHEQ->getSingleScalarResult();

        //count archived event
        $ARE = $this->em->createQueryBuilder();
        $ARE->select($ARE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.CheckArchive = 1');

        $AREQ = $ARE->getQuery();
        $ARER = $AREQ->getSingleScalarResult();

        //count users
        $acc = $this->em->createQueryBuilder();
        $acc->select($acc->expr()->count('u.id'))
            ->from(User::class, 'u');

        $accquery = $acc->getQuery();
        $accresult = $accquery->getSingleScalarResult();

        // render the template and pass the first name to it
        return $this->render('admin/index.html.twig', [
            'id'=>$id,
            'firstName' => $firstName,
            'secondName' => $secondName,
            'users' => $result_active_users,
            'events'=>$events,
            'countcompletedevents'=>$RCCE,
            'countActiveevents'=>$RCAE,
            'all'=>$result,
            'OnHoldEvent'=>$OHER,
            'Archived'=>$ARER,
            'accounts'=>$accresult,
        ]);
    }

    #[Route('/admin/calendar', name: 'app_admin_Calendar')]
    public function admin_settings(UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        $id = $user->getId();
        $firstName = $user->getFirstName();
        $secondName = $user->getSecondName();

        //get current date
        $currentDate = new DateTime();
        $currentDate->setTime(0, 0, 0);

        $events = $this->getDoctrine()->getRepository(Events::class)->findAll();

        $rdvs = [];
        foreach($events as $event){
            $rdvs[] = [
                'id' => $event->getId(),
                'start' => $event->getDateStart()->format('Y-m-d H:i:s'),
                'end' => $event->getDateEnd()->format('Y-m-d H:i:s'),
                'title' => $event->getTitle(),
                'description' => $event->getDiscription(),
            ];
        }
        $data = json_encode($rdvs);


        //count Completed events
        $countCE = $this->em->createQueryBuilder();
        $countCE->select($countCE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.DateEnd < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $countCEquery = $countCE->getQuery();
        $RCCE = $countCEquery->getSingleScalarResult();

        //count active events
        $countAE = $this->em->createQueryBuilder();
        $countAE->select($countAE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.DateEnd > :today')
            ->andWhere('e.DateStart < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $countAEquery = $countAE->getQuery();
        $RCAE = $countAEquery->getSingleScalarResult();

        //count all events
        $count = $this->em->createQueryBuilder();
        $count->select($count->expr()->count('e.id'))
            ->from(Events::class, 'e');

        $query = $count->getQuery();
        $result = $query->getSingleScalarResult();

        //count on hold events
        $OHE = $this->em->createQueryBuilder();
        $OHE->select($OHE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.CheckValid = 0');

        $OHEQ = $OHE->getQuery();
        $OHER = $OHEQ->getSingleScalarResult();

        //count archived event
        $ARE = $this->em->createQueryBuilder();
        $ARE->select($ARE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.CheckArchive = 1');

        $AREQ = $ARE->getQuery();
        $ARER = $AREQ->getSingleScalarResult();

        //count users
        $acc = $this->em->createQueryBuilder();
        $acc->select($acc->expr()->count('u.id'))
            ->from(User::class, 'u');

        $accquery = $acc->getQuery();
        $accresult = $accquery->getSingleScalarResult();



        // render the template and pass the first name to it
        return $this->render('admin/Event/calendar.html.twig', [
            'firstName' => $firstName,
            'secondName' => $secondName,
            'events' => $events,
            'countcompletedevents'=>$RCCE,
            'countActiveevents'=>$RCAE,
            'all'=>$result,
            'OnHoldEvent'=>$OHER,
            'Archived'=>$ARER,
            'accounts'=>$accresult,
            'data'=>$data,
            'id'=>$id,
        ]);
    }

    #[Route('/admin/Details', name: 'app_admin_details')]
    public function admin_details(UserRepository $userRepository,EventsRepository $eventsRepository,ParticipantsRepository $participantsRepository): Response
    {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $id = $user->getId();
        $firstName = $user->getFirstName();
        $secondName = $user->getSecondName();
        $currentDate = new DateTime();
        $currentDate->setTime(0, 0, 0);
        $users = $userRepository->findAll();
        $events = $eventsRepository->findAll();

        $qb = $em->createQueryBuilder();
        $qb->select('p.id_user,p.id_event')
            ->from(Participants::class, 'p');
        $query = $qb->getQuery();
        $idUsers = $query->getResult();



        //Find completed event
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Events::class, 'e')
            ->where('e.DateEnd < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $query = $qb->getQuery();
        $completed = $query->getResult();


        //find the user who sugg the event
        $sugg = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->innerJoin(Events::class,'e','WITH','e.id_user = u.id')
            ->getQuery();
        $sugg_result = $sugg->getResult();



        //Find active event
        $activated = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Events::class, 'e')
            ->where('e.DateEnd > :today')
            ->andWhere('e.DateStart < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $queryActive = $activated->getQuery();
        $active = $queryActive->getResult();

        //count Completed events
        $countCE = $this->em->createQueryBuilder();
        $countCE->select($countCE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.DateEnd < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $countCEquery = $countCE->getQuery();
        $RCCE = $countCEquery->getSingleScalarResult();

        //count active events
        $countAE = $this->em->createQueryBuilder();
        $countAE->select($countAE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.DateEnd > :today')
            ->andWhere('e.DateStart < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $countAEquery = $countAE->getQuery();
        $RCAE = $countAEquery->getSingleScalarResult();

        //count all events
        $count = $this->em->createQueryBuilder();
        $count->select($count->expr()->count('e.id'))
            ->from(Events::class, 'e');

        $query = $count->getQuery();
        $result = $query->getSingleScalarResult();

        //count on hold events
        $OHE = $this->em->createQueryBuilder();
        $OHE->select($OHE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.CheckValid = 0');

        $OHEQ = $OHE->getQuery();
        $OHER = $OHEQ->getSingleScalarResult();

        //count archived event
        $ARE = $this->em->createQueryBuilder();
        $ARE->select($ARE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.CheckArchive = 1');

        $AREQ = $ARE->getQuery();
        $ARER = $AREQ->getSingleScalarResult();

        //count users
        $acc = $this->em->createQueryBuilder();
        $acc->select($acc->expr()->count('u.id'))
            ->from(User::class, 'u');

        $accquery = $acc->getQuery();
        $accresult = $accquery->getSingleScalarResult();

        // render the template and pass the first name to it
        return $this->render('admin/Event/details.html.twig', [
            'firstName' => $firstName,
            'secondName' => $secondName,
            'users' => $users,
            'events'=>$events,
            'Today'=>$currentDate,
            'CE'=>$completed,
            'AE'=>$active,
            'countcompletedevents'=>$RCCE,
            'countActiveevents'=>$RCAE,
            'all'=>$result,
            'OnHoldEvent'=>$OHER,
            'Archived'=>$ARER,
            'accounts'=>$accresult,
            'id'=>$id,
            'foundsuggeventusers'=>$sugg_result,
            'idUsers' => $idUsers,
        ]);
    }


    #[Route('/admin/Details/display/{identidfiant}', name: 'app_admin_display', methods: 'GET')]
    public function admin_displays(UserRepository $userRepository,EventsRepository $eventsRepository,int $identidfiant): Response
    {

        $user = $this->getUser();
        $id = $user->getId();
        $firstName = $user->getFirstName();
        $secondName = $user->getSecondName();
        $currentDate = new DateTime();
        $currentDate->setTime(0, 0, 0);
        $users = $userRepository->findAll();
        $events = $eventsRepository->findAll();

        //find selected event to display
        $entityManager = $this->getDoctrine()->getManager();

        $item = $entityManager->getRepository(Events::class)->find($identidfiant);
        if (!$item) {
            throw $this->createNotFoundException('Item not found');
        }

        //Find completed event
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Events::class, 'e')
            ->where('e.DateEnd < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $query = $qb->getQuery();
        $completed = $query->getResult();

        //Find active event
        $activated = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Events::class, 'e')
            ->where('e.DateEnd > :today')
            ->andWhere('e.DateStart < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $queryActive = $activated->getQuery();
        $active = $queryActive->getResult();

        //count Completed events
        $countCE = $this->em->createQueryBuilder();
        $countCE->select($countCE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.DateEnd < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $countCEquery = $countCE->getQuery();
        $RCCE = $countCEquery->getSingleScalarResult();

        //count active events
        $countAE = $this->em->createQueryBuilder();
        $countAE->select($countAE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.DateEnd > :today')
            ->andWhere('e.DateStart < :today')
            ->andWhere('e.CheckValid = 1')
            ->setParameter('today', $currentDate);

        $countAEquery = $countAE->getQuery();
        $RCAE = $countAEquery->getSingleScalarResult();

        //count all events
        $count = $this->em->createQueryBuilder();
        $count->select($count->expr()->count('e.id'))
            ->from(Events::class, 'e');

        $query = $count->getQuery();
        $result = $query->getSingleScalarResult();

        //count on hold events
        $OHE = $this->em->createQueryBuilder();
        $OHE->select($OHE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.CheckValid = 0');

        $OHEQ = $OHE->getQuery();
        $OHER = $OHEQ->getSingleScalarResult();

        //count archived event
        $ARE = $this->em->createQueryBuilder();
        $ARE->select($ARE->expr()->count('e.id'))
            ->from(Events::class, 'e')
            ->where('e.CheckArchive = 1');

        $AREQ = $ARE->getQuery();
        $ARER = $AREQ->getSingleScalarResult();

        //count users
        $acc = $this->em->createQueryBuilder();
        $acc->select($acc->expr()->count('u.id'))
            ->from(User::class, 'u');

        $accquery = $acc->getQuery();
        $accresult = $accquery->getSingleScalarResult();

        // render the template and pass the first name to it
        return $this->render('admin/Event/display.html.twig', [
            'firstName' => $firstName,
            'secondName' => $secondName,
            'users' => $users,
            'events'=>$events,
            'Today'=>$currentDate,
            'CE'=>$completed,
            'AE'=>$active,
            'countcompletedevents'=>$RCCE,
            'countActiveevents'=>$RCAE,
            'all'=>$result,
            'OnHoldEvent'=>$OHER,
            'Archived'=>$ARER,
            'accounts'=>$accresult,
            'id'=>$id,
            'displayitem'=>$item,
        ]);

    }





}
