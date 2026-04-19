<?php

namespace App\Tests\Application\Controller;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AuthControllerTest extends WebTestCase
{
    public function testLoginPageShow(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testLoginSuccessWithCorrectCredentials(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        
        $this->assertResponseIsSuccessful();
        
        // Tester la connexion à partir d'un utilisateur existant
        // Email et mot de passe corrects

        // Créer un utilisateur en base
        $objUser = UserFactory::createOne();

        $client->submitForm('Se connecter', [
            '_username' => $objUser->getMail(),
            '_password' => 'password'
        ]);

        // Valide que la connexion s'est bien passée et que l'on est redirigé vers l'URL '/'
        $this->assertResponseRedirects();

        $client->followRedirect();
        $this->assertRouteSame('app_tournament');
    }
    
    public function testLoginFailedWithBadPassword(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        
        $this->assertResponseIsSuccessful();

        $objUser = UserFactory::createOne();

        $client->submitForm('Se connecter', [
            '_username' => $objUser->getmail(),
            '_password' => "BadPassword"
        ]);

        // Redirection en cas d'erreur vers la page login
        $this->assertResponseRedirects();  

        $client->followRedirect();
        $this->assertRouteSame('app_login');    
        
        // On vérifie qu'un message d'erreur est bien présent
        $this->assertAnySelectorTextContains('div', 'Invalid credentials.');
    }
    
    public function testLoginFailedWithBadEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        
        $this->assertResponseIsSuccessful();

        $client->submitForm('Se connecter', [
            '_username' => "NotExist@mail.com",
            '_password' => "BadPassword"
        ]);

        // Redirection en cas d'erreur vers la page login
        $this->assertResponseRedirects();  

        $client->followRedirect();
        $this->assertRouteSame('app_login');    
        
        // On vérifie qu'un message d'erreur est bien présent
        $this->assertAnySelectorTextContains('div', 'Invalid credentials.');
        
    }
}