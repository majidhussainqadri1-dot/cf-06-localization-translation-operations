<?php

declare(strict_types=1);

namespace Sabri\Localization\Domain\Future;

use DateTimeImmutable;
use InvalidArgumentException;

final class HotfixApprovalGuard
{
    public static function normalize(array $input): array
    {
        $approvals=$input['approvals']??null;
        if(!is_array($approvals)||count($approvals)<2||count($approvals)>20){throw new InvalidArgumentException('Emergency hotfix approvals are incomplete or exceed the bounded limit.');}
        $now=time();
        foreach($approvals as $approval){
            if(!is_array($approval)){throw new InvalidArgumentException('Emergency hotfix approval record is invalid.');}
            $actor=trim((string)($approval['actor_id']??''));
            if(1!==preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:@-]{0,127}$/D',$actor)){throw new InvalidArgumentException('Emergency hotfix approval actor identity is invalid.');}
            $raw=trim((string)($approval['approved_at']??''));
            if(1!==preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D',$raw)){throw new InvalidArgumentException('Emergency hotfix approval timestamp must be strict ISO-8601 evidence.');}
            $parsed=date_parse($raw);
            if(($parsed['warning_count']??0)>0||($parsed['error_count']??0)>0){throw new InvalidArgumentException('Emergency hotfix approval timestamp is not a real calendar instant.');}
            try{$at=new DateTimeImmutable($raw);}catch(\Throwable){throw new InvalidArgumentException('Emergency hotfix approval timestamp is invalid.');}
            $age=$now-$at->getTimestamp();
            if($age< -60||$age>900){throw new InvalidArgumentException('Emergency hotfix approval must be fresh within fifteen minutes.');}
        }
        $evidence=trim((string)($input['evidence_ref']??''));
        if(''===$evidence||strlen($evidence)>191){throw new InvalidArgumentException('Emergency hotfix evidence_ref is missing or oversized.');}
        return $input;
    }
}
