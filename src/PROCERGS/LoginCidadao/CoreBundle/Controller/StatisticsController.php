<?php
namespace PROCERGS\LoginCidadao\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use PROCERGS\LoginCidadao\CoreBundle\Form\Type\PersonFilterFormType;
use PROCERGS\LoginCidadao\CoreBundle\Helper\GridHelper;

/**
 * @Route("/statistics")
 */
class StatisticsController extends Controller
{

    /**
     * @Route("/", name="lc_statistics")
     * @Template()
     */
    public function indexAction(Request $request)
    {
      return true;
    }

    /**
     * @Route("/users/badges", name="lc_statistics_user_badge")
     * @Template()
     */
    public function usersByBadgesAction() {
      $badgesHandler = $this->get('badges.handler');

      $badges = $badgesHandler->getAvailableBadges();
      foreach ($badges as $client => $badge) {
        foreach ($badge as $name => $desc) {
          $filterBadge = new \PROCERGS\LoginCidadao\BadgesBundle\Model\Badge($client, $name, 2);
          $count = $badgesHandler->countBearers($filterBadge);
          $b = array_shift($count);
          $data[$client][] = $b;
        }
      }
      return array("data" => $data);
    }

    /**
     * @Route("/users/region/{type}", name="lc_statistics_user_region")
     * @Template()
     */
    public function usersByRegionAction($type) {
      $em = $this->getDoctrine()->getManager();
      $repo = $em->getRepository('PROCERGSLoginCidadaoCoreBundle:Person');
      if ($type == "country") {
        $data = $repo->getCountByCountry();
      } else {
        $data = $repo->getCountByState();
      }

      return $this->render('PROCERGSLoginCidadaoCoreBundle:Statistics:usersByRegion.html.twig', array('data' => $data ));
    }

    /**
     * @Route("/users/city/{stateId}", name="lc_statistics_user_city")
     * @Template()
     */
    public function usersByCityAction($stateId) {
      $em = $this->getDoctrine()->getManager();
      $repo = $em->getRepository('PROCERGSLoginCidadaoCoreBundle:Person');
      $data = $repo->getCountByCity($stateId);

      return $this->render('PROCERGSLoginCidadaoCoreBundle:Statistics:usersByCity.html.twig', array('data' => $data ));
    }

    /**
     * @Route("/users/services", name="lc_statistics_user_services")
     * @Template()
     */
    public function usersByServicesAction() {
      $em = $this->getDoctrine()->getManager();
      $repo = $em->getRepository('PROCERGSOAuthBundle:Client');
      $data = $repo->getCountPerson();

      return array("data" => $data);
    }

  }