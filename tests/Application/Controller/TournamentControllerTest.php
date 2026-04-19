<?php
namespace App\Tests\Application\Controller;

use App\Factory\TournamentFactory;
use App\Factory\UserFactory;
use App\Factory\RegisteredFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class TournamentControllerTest extends WebTestCase
{
    public function testTournamentHomePageShow(): void
    {
        $client = static::createClient();
        $client->request('GET', '/tournament/home');
        $this->assertResponseIsSuccessful();
    }

    public function testTournamentListPageShow(): void
    {
        $client = static::createClient();
        $client->request('GET', '/tournament/list');
        $this->assertResponseIsSuccessful();
    }

    public function testTournamentListSearch(): void
    {
        $client = static::createClient();
        TournamentFactory::createOne([
            'name' => 'Super Tournoi Test',
            'isStarted' => false,
        ]);
        $client->request('GET', '/tournament/list?search=Super');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Super Tournoi Test');
    }

    public function testCreateTournamentPageRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/tournament/create');
        $this->assertResponseRedirects('/login');
    }

    public function testCreateTournamentPageShowWhenLoggedIn(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);
        $client->request('GET', '/tournament/create');
        $this->assertResponseIsSuccessful();
    }

    public function testCreateTournamentSuccess(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);
        $client->request('GET', '/tournament/create');

        $client->submitForm('Créer le tournoi', [
            'tournament_form[name]' => 'Mon tournoi test',
            'tournament_form[discipline]' => 'League of Legends',
            'tournament_form[mail]' => 'contact@test.com',
            'tournament_form[date]' => '2027-01-01',
            'tournament_form[end_date]' => '2027-01-02',
            'tournament_form[agreeTerms]' => true,
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_tournament');
    }

    public function testTournamentShowPage(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $tournament = TournamentFactory::createOne([
            'isStarted' => false,
            'createdBy' => $user,
        ]);

        $client->request('GET', '/tournament/' . $tournament->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testTournamentShowPageNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/tournament/99999');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testEditTournamentRequiresLogin(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $tournament = TournamentFactory::createOne([
            'createdBy' => $user,
        ]);

        $client->request('GET', '/tournament/' . $tournament->getId() . '/edit');
        $this->assertResponseRedirects('/login');
    }

    public function testEditTournamentForbiddenForNonAdmin(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $tournament = TournamentFactory::createOne([
            'createdBy' => $user,
        ]);

        $nonAdmin = UserFactory::createOne();
        $client->loginUser($nonAdmin);
        $client->request('GET', '/tournament/' . $tournament->getId() . '/edit');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testEditTournamentAccessibleForAdmin(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $tournament = TournamentFactory::createOne([
            'isStarted' => false,
            'createdBy' => $user,
        ]);

        RegisteredFactory::createOne([
            'user' => $user,
            'tournament' => $tournament,
            'role' => 'admin',
        ]);

        $client->loginUser($user);
        $client->request('GET', '/tournament/' . $tournament->getId() . '/edit');
        $this->assertResponseIsSuccessful();
    }

    public function testRegisterToTournament(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $tournament = TournamentFactory::createOne([
            'isStarted' => false,
            'createdBy' => $user,
        ]);

        $player = UserFactory::createOne();
        $client->loginUser($player);
        $client->request('POST', '/tournament/' . $tournament->getId() . '/register', [
            '_token' => 'register' . $tournament->getId(),
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_tour_show', ['id' => $tournament->getId()]);
    }

    public function testRegisterToTournamentRequiresLogin(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $tournament = TournamentFactory::createOne([
            'isStarted' => false,
            'createdBy' => $user,
        ]);

        $client->request('POST', '/tournament/' . $tournament->getId() . '/register', [
            '_token' => 'register' . $tournament->getId(),
        ]);

        $this->assertResponseRedirects('/login');
    }

    public function testDeleteTournamentSuccess(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $tournament = TournamentFactory::createOne([
            'isStarted' => false,
            'createdBy' => $user,
        ]);

        RegisteredFactory::createOne([
            'user' => $user,
            'tournament' => $tournament,
            'role' => 'admin',
        ]);

        $client->loginUser($user);
        $client->request('POST', '/tournament/' . $tournament->getId(), [
            '_token' => 'delete' . $tournament->getId(),
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_tournament');
    }

    public function testDeleteTournamentForbiddenForNonAdmin(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $tournament = TournamentFactory::createOne([
            'createdBy' => $user,
        ]);

        $nonAdmin = UserFactory::createOne();
        $client->loginUser($nonAdmin);
        $client->request('POST', '/tournament/' . $tournament->getId(), [
            '_token' => 'delete' . $tournament->getId(),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}