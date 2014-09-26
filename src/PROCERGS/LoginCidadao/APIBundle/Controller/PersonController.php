<?php

namespace PROCERGS\LoginCidadao\APIBundle\Controller;

use FOS\RestBundle\Controller\Annotations as REST;
use JMS\Serializer\SerializationContext;
use PROCERGS\LoginCidadao\APIBundle\Exception\RequestTimeoutException;
use PROCERGS\LoginCidadao\CoreBundle\Entity\Person;
use PROCERGS\LoginCidadao\CoreBundle\Entity\Authorization;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use PROCERGS\LoginCidadao\CoreBundle\Entity\Notification\Notification;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class PersonController extends BaseController
{

    /**
     * Gets the currently authenticated user.
     *
     * The returned object contents will depend on the scope the user authorized.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Gets the currently authenticated user.",
     *   output = {
     *     "class"="PROCERGS\LoginCidadao\CoreBundle\Entity\Person",
     *     "groups" = {"public_profile"}
     *   },
     *   statusCodes = {
     *     200 = "Returned when successful"
     *   }
     * )
     * @REST\Get("/person")
     * @REST\View(templateVar="person")
     * @throws NotFoundHttpException
     */
    public function selfAction()
    {
        $person = $this->preparePerson($this->getUser());
        $scope = $this->getClientScope($person);

        $view = $this->view($person)
                ->setSerializationContext($this->getSerializationContext($scope));
        return $this->handleView($view);
    }

    /**
     * Waits for a change in the current user's profile.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Waits for a change in the current user's profile.",
     *   output = {
     *     "class"="PROCERGS\LoginCidadao\CoreBundle\Entity\Person",
     *     "groups" = {"public_profile"}
     *   },
     *   statusCodes = {
     *     200 = "Returned when successful",
     *     408 = "Returned when the request times out"
     *   }
     * )
     * @REST\Get("/wait/person/update")
     * @REST\View
     */
    public function waitPersonChangeAction()
    {
        $user = $this->getUser();
        $scope = $this->getClientScope($user);
        $updatedAt = \DateTime::createFromFormat('Y-m-d H:i:s',
                        $this->getRequest()->get('updated_at'));

        if (!($updatedAt instanceof \DateTime)) {
            $updatedAt = new \DateTime();
        }

        $id = $user->getId();
        $lastUpdatedAt = null;
        $callback = $this->getCheckUpdateCallback($id, $updatedAt,
                $lastUpdatedAt);
        $person = $this->runTimeLimited($callback);
        $context = SerializationContext::create()->setGroups($scope);
        $view = $this->view($this->preparePerson($person))
                ->setSerializationContext($context);
        return $this->handleView($view);
    }

    private function runTimeLimited($callback, $waitTime = 1)
    {
        $maxExecutionTime = ini_get('max_execution_time');
        $limit = $maxExecutionTime ? $maxExecutionTime - 2 : 60;
        $startTime = time();
        while ($limit > 0) {
            $result = call_user_func($callback);
            $delta = time() - $startTime;

            if ($result !== false) {
                return $result;
            }

            $limit -= $delta;
            if ($limit <= 0) {
                break;
            }
            $startTime = time();
            sleep($waitTime);
        }
        throw new RequestTimeoutException("Request Timeout");
    }

    private function getCheckUpdateCallback($id, $updatedAt, $lastUpdatedAt)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $people = $em->getRepository('PROCERGSLoginCidadaoCoreBundle:Person');
        return function() use ($id, $people, $em, $updatedAt, $lastUpdatedAt) {
            $em->clear();
            $person = $people->find($id);
            if (!$person->getUpdatedAt()) {
                return false;
            }

            if ($person->getUpdatedAt() > $updatedAt) {
                return $person;
            }

            if ($lastUpdatedAt === null) {
                $lastUpdatedAt = $person->getUpdatedAt();
            } elseif ($person->getUpdatedAt() != $lastUpdatedAt) {
                return $person;
            }

            return false;
        };
    }

    /**
     * @REST\Post("/person/sendnotification")
     * @REST\View
     * @deprecated since version 1.0.2
     */
    public function sendNotificationAction(Request $request)
    {
        $token = $this->get('security.context')->getToken();
        $accessToken = $this->getDoctrine()->getRepository('PROCERGSOAuthBundle:AccessToken')->findOneBy(array('token' => $token->getToken()));
        $client = $accessToken->getClient();

        $body = json_decode($request->getContent(), 1);

        $chkAuth = $this->getDoctrine()
                ->getManager()
                ->getRepository('PROCERGSLoginCidadaoCoreBundle:Authorization')
                ->createQueryBuilder('a')
                ->select('cnc, p')
                ->join('PROCERGSLoginCidadaoCoreBundle:Person', 'p', 'WITH',
                        'a.person = p')
                ->join('PROCERGSOAuthBundle:Client', 'c', 'WITH', 'a.client = c')
                ->join('PROCERGSLoginCidadaoCoreBundle:ConfigNotCli', 'cnc',
                        'WITH', 'cnc.client = c')
                ->where('c.id = ' . $client->getId() . ' and p.id = :person_id and cnc.id = :config_id')
                ->getQuery();
        $rowR = array();
        $em = $this->getDoctrine()->getManager();
        $validator = $this->get('validator');

        foreach ($body as $idx => $row) {
            if (isset($row['person_id'])) {
                $res = $chkAuth->setParameters(array('person_id' => $row['person_id'], 'config_id' => $row['config_id']))->getResult();
                if (!$res) {
                    $rowR[$idx] = array('person_id' => $row['person_id'], 'error' => 'missing authorization or configuration');
                    continue;
                }
                $not = new Notification();
                $not->setPerson($res[0]);
                $not->setConfigNotCli($res[1])
                        ->setIcon(isset($row['icon']) && $row['icon'] ? $row['icon'] : $not->getConfigNotCli()->getIcon())
                        ->setTitle(isset($row['title']) && $row['title'] ? $row['title'] : $not->getConfigNotCli()->getTitle())
                        ->setShortText(isset($row['shorttext']) && $row['shorttext'] ? $row['shorttext'] : $not->getConfigNotCli()->getShortText())
                        ->setText($row['text'])
                        ->parseHtmlTpl($not->getConfigNotCli()->getHtmlTpl());
                $errors = $validator->validate($not);
                if (!count($errors)) {
                    $em->persist($not);
                    $rowR[$idx] = array('person_id' => $row['person_id'], 'notification_id' => $not->getId());
                } else {
                    $rowR[$idx] = array('person_id' => $row['person_id'], 'error' => (string) $errors);
                }
            }
        }
        $em->flush();
        return $this->handleView($this->view($rowR));
    }

}
