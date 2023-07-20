<?php

namespace App\Controller;

use App\Entity\Card;
use App\Entity\CardDetails;
use App\Entity\User;
use Doctrine\ORM\Mapping\Id;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class CardController extends AbstractController
{
    //cards
    #[Route('/addCard', name: 'card.add')]
    public function addCard(Request $request, ManagerRegistry $doctrine): Response
    {
        /*$logger->info('Processing addCard action');
        $logger->debug('Request payload:', [$request->getContent()]);*/
        // Handle the actual POST request logic
        $data = json_decode($request->getContent(), true);

        $entityManager = $doctrine->getManager();
        $card = new Card();
        $card->setOwner($data['owner']);

        $entityManager->persist($card);
        $entityManager->flush();

        return new JsonResponse($data);
    }

    #[Route('/addCardForUser', name: 'card.addForUser')]
    public function addCardForUser(Request $request, ManagerRegistry $doctrine): Response
    {
        $data = json_decode($request->getContent(), true);

        $entityManager = $doctrine->getManager();

        $repository = $doctrine->getRepository(CardDetails::class);
        $cardDetails = $repository->findOneBy(['code'=>$data['code']]);

        if(!$cardDetails)
            return new JsonResponse(['message' => "Card doesn't exist"]);
        
        $repository = $doctrine->getRepository(User::class);
        $user = $repository->find($data['id']);

        $repository = $doctrine->getRepository(Card::class);
        $card = $repository->findOneBy(['cardDetails'=>$cardDetails]);

        $user->addCard($card);

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse();
    }

    #[Route('/getCardsByUser/{id}', name: 'card.listCardByUser')]
    public function getCardsByUser(ManagerRegistry $doctrine, $id)
    {
        $repository = $doctrine->getRepository(User::class);
        $userCards = $repository->find($id)->getCards();

        $cards = array();
        foreach($userCards as $key => $card){
            $cards[$key]['id'] = $card->getId();
            $cards[$key]['owner'] = $card->getOwner();
            $cards[$key]['cardDetails'] = $card->getCardDetails();
        }
        $res = array("result" => $cards);
        return new JsonResponse($res);
    }

    #[Route('/getCards', name: 'card.list')]
    public function getCards(ManagerRegistry $doctrine)
    {
        $repository = $doctrine->getRepository(Card::class);
        $struct = $repository->findAll();
        $cards = array();
        foreach($struct as $key => $card){
            $cards[$key]['id'] = $card->getId();
            $cards[$key]['owner'] = $card->getOwner();
            $cards[$key]['cardDetails'] = $card->getCardDetails();
        }
        $res = array("result" => $cards);
        return new JsonResponse($res);
    }

    //formCards
    #[Route('/formCardDetails', name: 'card.form')]
    public function formCardDetails(Request $request,SluggerInterface $slugger, ManagerRegistry $doctrine)
    {
        $entityManager = $doctrine->getManager();

        $fname = $request->request->get('fname');
        $lname = $request->request->get('lname');
        $email = $request->request->get('email');
        $tel = $request->request->get('tel');
        $about = $request->request->get('about');
        $facebook = $request->request->get('facebook');
        $instagram = $request->request->get('instagram');
        $youtube = $request->request->get('youtube');
        $linkedin = $request->request->get('linkedin');
        $idCard = $request->request->get('idCard');
        $code = $request->request->get('code');

        $profilePicture = $request->files->get('profilePicture');
        $icon = $request->files->get('icon');
        $codeimage=uniqid();

        $repository = $doctrine->getRepository(Card::class);
        $card = $repository->find($idCard);

        if ($profilePicture) {
            $originalFilename = pathinfo($profilePicture->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename1 = $codeimage . $safeFilename.'.'.$profilePicture->guessExtension();
            $profilePicture->move(
                $this->getParameter('profile_picture_directory'),
                $newFilename1
            );
        }

        if ($icon) {
            $originalFilename = pathinfo($icon->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename2 = $codeimage . $safeFilename.'.'.$icon->guessExtension();
            $icon->move(
                $this->getParameter('icon_directory'),
                $newFilename2
            );
        }

        if($card->getCardDetails()!=null){
            $cardDetails = $card->getCardDetails();
            
            if($cardDetails->getPhoto()!=null && $profilePicture)
            {
                $filesystem = new Filesystem();
                $filesystem->remove(new File($this->getParameter('profile_picture_directory').'/'.$cardDetails->getPhoto()));
            }

            if($cardDetails->getLogo()!=null && $icon)
            {
                $filesystem = new Filesystem();
                $filesystem->remove(new File($this->getParameter('icon_directory').'/'.$cardDetails->getLogo()));
            }
        }else
            $cardDetails = new CardDetails();

        $cardDetails->setFirstName($fname)
            ->setLastName($lname)
            ->setEmail($email)
            ->setPhone($tel)
            ->setAbout($about)
            ->setFacebook($facebook)
            ->setInstagram($instagram)
            ->setYoutube($youtube)
            ->setLinkedin($linkedin);

        if ($profilePicture) {
            $cardDetails->setPhoto($newFilename1);
        }

        if ($icon) {
            $cardDetails->setLogo($newFilename2);
        }
        
        if($code==''){
            $code = uniqid();
            $cardDetails->setCode($code);
        }

        $card->setCardDetails($cardDetails);

        $entityManager->persist($cardDetails);
        $entityManager->persist($card);
        $entityManager->flush();
        
        return new JsonResponse();
    }

    //cardDetails
    #[Route('/getCards/{idCard}', name: 'card.detail')]
    public function getCardSetails(ManagerRegistry $doctrine, $idCard)
    {
        $repository = $doctrine->getRepository(Card::class);
        $card = $repository->find($idCard);
        $cardDetails = $card->getCardDetails();

        $photoPath = $this->getParameter('app.base_url').'/uploads/profile_pictures/'.$cardDetails->getPhoto();
        $iconPath = $this->getParameter('app.base_url').'/uploads/icons/'.$cardDetails->getLogo();

        $responseData = [
            'id' => $cardDetails->getId(),
            'firstName' => $cardDetails->getFirstName(),
            'lastName' => $cardDetails->getLastName(),
            'email' => $cardDetails->getEmail(),
            'phone' => $cardDetails->getPhone(),
            'about' => $cardDetails->getAbout(),
            'photo' => $photoPath,
            'logo' => $iconPath,
            'facebook' => $cardDetails->getFacebook(),
            'instagram' => $cardDetails->getInstagram(),
            'youtube' => $cardDetails->getYoutube(),
            'linkedin' => $cardDetails->getLinkedin(),
            'code' => $cardDetails->getCode(),
        ];
        return new JsonResponse($responseData);
    }

    //users
    #[Route('/addUser', name: 'user.add')]
    public function addUser(Request $request, ManagerRegistry $doctrine): Response
    {
        $data = json_decode($request->getContent(), true);

        $entityManager = $doctrine->getManager();
        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword(password_hash($data['password'], PASSWORD_BCRYPT));
        $user->setRole("user");

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse(['id'=>$user->getId()]);
    }

    #[Route('/getUser/{email}/{password}', name: 'user.get')]
    public function userGetter($email, $password, ManagerRegistry $doctrine): Response
    {
        $repository = $doctrine->getRepository(User::class);
        $user = $repository->findOneBy(['email' => $email]);

        if (!$user) {
            return new JsonResponse(['message' => 'User not found']);
        }

        if (password_verify($password, $user->getPassword())) {

            $responseData=[
                'id'=> $user->getId(),
                'email'=> $user->getEmail(),
                'role'=> $user->getRole()
            ];
    
            return new JsonResponse(['user' => $responseData]);

        } else {
            return new JsonResponse(['message' => 'Incorrect password']);
        }
    }

    #[Route('/sendCode', name: 'user.code')]
    public function sendEmail(Request $request, MailerInterface $mailer, ManagerRegistry $doctrine): Response
    {
        $mail = json_decode($request->getContent(), true);

        $repository = $doctrine->getRepository(User::class);
        $user = $repository->findOneBy(['email' => $mail]);

        if (!$user) {
            return new JsonResponse(['message' => 'User not found']);
        }else{

            $code = rand(0,9) . rand(0,9) . rand(0,9) . rand(0,9) . rand(0,9);

            $email = (new Email())
                ->from('iliassayagh@gmail.com')
                ->to($user->getEmail())
                ->subject('your recovery code')
                ->text('Copy this code to confirm your identity: ' . $code)
                ->html('<p>Copy this code to confirm your identity: ' . $code . '</p>');

            $mailer->send($email);

            return new JsonResponse(['code' => $code]);
        }
    }

    #[Route('/updatePwd', name: 'user.updatePwd')]
    public function updatePwd(Request $request, ManagerRegistry $doctrine): Response
    {
        $data = json_decode($request->getContent(), true);

        $repository = $doctrine->getRepository(User::class);
        $user = $repository->findOneBy(['email' => $data['email']]);
        $user->setPassword(password_hash($data['pwd'], PASSWORD_BCRYPT));

        $entityManager = $doctrine->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse();
    }

}
