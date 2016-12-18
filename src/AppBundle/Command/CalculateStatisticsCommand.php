<?php

/*
 * This file is part of the Arnapou GW2Tools package.
 *
 * (c) Arnaud Buathier <arnaud@arnapou.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppBundle\Command;

use AppBundle\Entity\Token;
use Arnapou\GW2Api\Cache\MongoCache;
use Arnapou\GW2Api\Exception\InvalidTokenException;
use Gw2tool\Account;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CalculateStatisticsCommand extends AbstractCommand {

    protected function configure() {
        $this
            ->setName('gw2tool:statistics')
            ->setDescription('Calculate statistics.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $env        = $this->getGwEnvironment('en');
        $cache      = $env->getCache(); /* @var $cache MongoCache */
        $collection = $cache->getMongoDB()->selectCollection('statistics');
        $manager    = $this->getDoctrine()->getManager();
        $repo       = $this->getDoctrine()->getRepository(Token::class);

        foreach ($repo->findBy(['valid' => 1]) as /* @var $token Token */ $token) {
            try {
                if ($token->getLastaccess() < time() - 86400) {
                    $env->setAccessToken((string) $token);
                    $account    = new Account($env);
                    $account->getName(); // used only to trigger InvalidTokenException if something is wrong
                    $calculated = $account->calculateStatistics($collection);

                    if ($calculated) {
                        $output->writeln("statistics calclulated for <info>" . $token->getName() . "</info>");
                    }
                }
            }
            catch (InvalidTokenException $ex) {
                $token->setIsValid(false);
                $manager->persist($token);
                $manager->flush();

                $output->writeln("statistics calculated for <info>" . $token->getName() . "</info>");
            }
            catch (\Exception $ex) {
                $output->writeln("<error>" . $ex->getMessage() . "</error>");
            }
        }
    }

}