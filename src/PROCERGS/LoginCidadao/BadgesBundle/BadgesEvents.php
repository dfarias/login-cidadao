<?php

namespace PROCERGS\LoginCidadao\BadgesBundle;

final class BadgesEvents
{

    /**
     * The badges.evaluate event is thrown each time it's needed
     * to evaluate the badges for a Person object.
     * 
     * The event listener receives an
     * PROCERGS\LoginCidadao\BadgesBundle\Event\EvaluateBadgesEvent instance
     * 
     * @var string
     */
    const BADGES_EVALUATE = 'badges.evaluate';

    /**
     * The badges.list.available event is thrown each time it's needed
     * to list the available badges of the application.
     * 
     * The event listener receives an
     * PROCERGS\LoginCidadao\BadgesBundle\Event\ListBadgesEvent instance
     * 
     * @var string
     */
    const BADGES_LIST_AVAILABLE = 'badges.list.available';

    /**
     * The badges.register.evaluator event is thrown each time
     * an evaluator is registered.
     * 
     * The event listener receives an
     * PROCERGS\LoginCidadao\BadgesBundle\Model\BadgeEvaluatorInterface instance
     * 
     * @var string
     */
    const BADGES_REGISTER_EVALUATOR = 'badges.register.evaluator';

}