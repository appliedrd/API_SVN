<?php
/**
 * @author Raphael St-Arnaud
 */

require_once 'vendor/autoload.php';

/**
 * Allow communication with the Bitbucket Api
 */
class BitbucketApi
{
    private $client;

    /**
     * Allow communication with the Bitbucket Api
     * Dummy account : Required to bypass low request limit of Bitbucket
     */
    public function __construct(){
        $this->client = new \Bitbucket\API\Api();
        $this->client->getClient()->addListener(new \Bitbucket\API\Http\Listener\BasicAuthListener('fabulaChildBot', 'solarus45'));

    }

    /**
     * Get the commits of a repository
     * @param1 $owner owner of the repository
     * @param2 $repo name of the repository
     * @return Number of commits
     */
    public function getRepositoryCommits($owner, $repo) {

        $res = $this->getCommits($owner,$repo);

        return count($res);
    }

    /**
     * Get a list of contributors to a repository
     * @param1 $owner owner of the repository
     * @param2 $repo name of the repository
     * @return Array of contributors
     */
    public function getRepositoryContributors($owner, $repo) {
        $contributors = array();

        $commits = $this->getCommits($owner,$repo);

        foreach($commits as $commit){
            $author = $commit['author'];
            $userInfo = $author['user'];

            if(!in_array($userInfo['username'],$contributors))
                array_push($contributors,$userInfo['username']);
        }

        return $contributors;
    }

    /**
     * Get the number of pull requests (Only for the default branch)
     * @param1 $owner owner of the repository
     * @param2 $repo name of the repository
     * @param3 $state state of the pullRequest (open,closed,all)
     * @return Number of pull requests
     */
    public function getRepositoryPullRequests($owner, $repo, $state) {
        $count = 0;

        if($state == 'open'){
            $pulls= $this->getPullRequests($owner,$repo,'OPEN');
            $count += count($pulls);
        }
        else{
            $pulls = $this->getPullRequests($owner,$repo,'MERGED');
            $count += count($pulls);

            $pulls = $this->getPullRequests($owner,$repo,'DECLINED');
            $count += count($pulls);
        }

        return $count;
    }

    /**
     * Get the number of issues of a repository
     * @param1 $owner owner of the repository
     * @param2 $repo name of the repository
     * @param3 $state state of the issues (open,closed,all)
     * @return Number of issues
     */
    public function getRepositoryIssues($owner, $repo, $state) {

        $issue = new Bitbucket\API\Repositories\Issues();
        $response = $issue->all($owner, $repo, array('status' => $state));
        $res = json_decode($response->getContent(),true);

        return $res['count'];
    }

    /**
     * Get the information of a user
     * @param1 $user username of the user (login)
     * @return Array of information
     */
    public function getUserInfo($user) {

        $users = new Bitbucket\API\Users();

        $userInfo = $users->get($user);

        $infos = json_decode($userInfo->getContent(),true);

        $res = [
            'login' => $infos["username"],
            'name' => $infos["display_name"],
            'location' => $infos["location"],
        ];

        return $res;
    }

    /**
     * Get the number of commits made by a user of a repository
     * @param1 $user the username of the user (login)
     * @param2 $owner owner of the repository
     * @param3 $repo name of the repository
     * @return Number of commits
     */
    public function getUserCommits($user, $owner, $repo) {
        $nbCommits = 0;

        $commits = $this->getCommits($owner,$repo);

        error_reporting(0);

        foreach($commits as $commit){
            $author = $commit['author'];
            $userInfo = $author['user'];

            if($userInfo['username'] == $user);
                $nbCommits += 1;
        }

        error_reporting(-1);

        return $nbCommits;
    }

    /**
     * Get the number of pull requests made by a user of a repository
     * @param1 $user the username of the user (login)
     * @param2 $owner owner of the repository
     * @param3 $repo name of the repository
     * @param4 $state state of the pullRequest (open,closed,all)
     * @return Number of pull requests
     */
    public function getUserPullRequests($user, $owner, $repo, $state) {

        if($state == 'open'){
            $pulls = $this->getPullRequests($owner,$repo,'OPEN');
            $count = $this->filterPullRequests($user,$pulls['values']);
        }
        else{
            $pulls = $this->getPullRequests($owner,$repo,'MERGED');
            $count = $this->filterPullRequests($user,$pulls['values']);

            $pulls = $this->getPullRequests($owner,$repo,'DECLINED');
            $count += $this->filterPullRequests($user,$pulls['values']);
        }

        return $count;
    }

    /**
     * Access the number of repositories owned by the user
     * (No way of getting all repos he contributed to)
     * @param1 $user the username of the user (login)
     * @return Array of repositories
     */
    public function getUserRepositories($user) {

        $rep =  new Bitbucket\API\Repositories();
        $repoInfos =   $rep->all($user);

        $repos = json_decode($repoInfos->getContent(),true);

        $reps =array();

        foreach($repos['values'] as $repo){
            $info = $repo['links'];
            $info = $info['html'];
            array_push($reps,$info['href']);
        }

        return $reps;
    }

    /**
     * Get the number of issues assigned to a user
     * @param1 $user the username of the user (login)
     * @param2 $owner owner of the repository
     * @param3 $repo name of the repository
     * @param4 $state state of the pullRequest (open,closed,all)
     * @return Number of issues
     */
    public function getUserIssues($user, $owner, $repo, $state) {

        $issue = new Bitbucket\API\Repositories\Issues();
        $response = $issue->all($owner, $repo, array('status' => $state,'repsonsible' => $user));
        $res = json_decode($response->getContent(),true);

        return $res['count'];
    }

    /**
     * Get the commits in a repository (all branches).
     * @param1 $owner owner of the repository
     * @param2 $repo name of the repository
     * @return Array of commits
     */
    private function getCommits($owner,$repo){

        $commits = new Bitbucket\API\Repositories\Commits();
        $response = $commits->all($owner, $repo);

        $res = json_decode($response->getContent(), true);

        return $res['values'];
    }

    /**
     * Get the number of pull requests in a repository.
     * @param1 $owner owner of the repository
     * @param2 $repo name of the repository
     * @param3 $state state of the pull requests (OPEN,MERGED,CLOSED)
     * @return Array of pull requests
     */
    private function getPullRequests($owner, $repo, $state){
        $pull = new Bitbucket\API\Repositories\PullRequests();

        $response = $pull->all($owner, $repo, array('state' => $state));

        $res = json_decode($response->getContent(), true);

        return $res;
    }

    /**
     * Filter the pull requests for a user
     * @param1 $user the username of the user (login)
     * @param2 $pulls the pull requests
     * @return Number of filtered pull requests
     */
    private function filterPullRequests($user, $pulls){
        $count = 0;
        foreach($pulls as $pull){
            $author = $pull['author'];
            $username = $author['username'];

            if($username == $user)
                $count += 1;

        }

        return $count;
    }
}

?>