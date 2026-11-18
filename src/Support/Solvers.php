<?php

namespace Azcaptcha\Support;

/** Token helpers shared by the client; every call funnels through solve(). */
trait Solvers
{
    public function solveRecaptchaV2(
        string $sitekey,
        string $pageurl,
        bool $invisible = false,
        bool $enterprise = false,
        array $params = []
    ): string {
        return $this->solve('userrecaptcha', $params + [
            'googlekey' => $sitekey,
            'pageurl' => $pageurl,
            'invisible' => $invisible ? 1 : null,
            'enterprise' => $enterprise ? 1 : null,
        ]);
    }

    public function solveRecaptchaV3(
        string $sitekey,
        string $pageurl,
        string $action,
        float $minScore = 0.6,
        array $params = []
    ): string {
        return $this->solve('userrecaptcha', $params + [
            'googlekey' => $sitekey,
            'pageurl' => $pageurl,
            'version' => 'v3',
            'action' => $action,
            'min_score' => $minScore,
        ]);
    }

    public function solveHCaptcha(string $sitekey, string $pageurl, array $params = []): string
    {
        return $this->solve('hcaptcha', $params + ['sitekey' => $sitekey, 'pageurl' => $pageurl]);
    }

    public function solveTurnstile(string $sitekey, string $pageurl, array $params = []): string
    {
        return $this->solve('turnstile', $params + ['sitekey' => $sitekey, 'pageurl' => $pageurl]);
    }

    public function solveFunCaptcha(string $publickey, string $pageurl, array $params = []): string
    {
        return $this->solve('funcaptcha', $params + ['publickey' => $publickey, 'pageurl' => $pageurl]);
    }

    public function solveGeeTest(string $gt, string $challenge, string $pageurl, array $params = []): string
    {
        return $this->solve('geetest', $params + ['gt' => $gt, 'challenge' => $challenge, 'pageurl' => $pageurl]);
    }

}
