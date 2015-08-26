<?php

require_once 'vendor/autoload.php';

class GithubApi
{
    private $client;

    public function __construct(){
        $this->client = new \Github\Client();
        $this->client->authenticate("fabulaChildBot", "fabula45", Github\Client::AUTH_HTTP_PASSWORD);
    }

    public function getRepositoryCommits($owner, $repo) {

        $res = $this->client->api('repo')->commits()->all($owner, $repo, array('sha' => 'master'));

        return count($res);
    }

    public function getRepositoryContributors($owner, $repo) {

        $res = $this->client->api('repo')->contributors($owner, $repo);
        return count($res);
    }

    public function getRepositoryPullRequests($owner, $repo, $state) {

        $res = $this->client->api('pull_request')->all($owner, $repo, array('state' => $state));

        return count($res);
    }

    public function getRepositoryIssues($owner, $repo, $state) {

        $res = $this->client->api('issue')->all($owner, $repo, array('state' => $state));

        return count($res);
    }

    public function getUserInfo($user) {

        $infos =  $this->client->api('user')->show($user);

        $userInfos = [
            "name" => $infos["name"],
            "login" => $infos["login"],
            "location" => $infos["location"]
        ];

        return $userInfos;
    }

    public function getUserCommits($user) {

        //Pending
    }

    public function getUserPullRequests($user) {
        //Pending
    }

    public function getUserRepositories($user) {

        //Pending
    }
}

?>