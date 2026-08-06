<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class ContentLinkService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly AuditRepository $audit,
        private readonly Transaction $tx
    ) {}

    public function register(array $input): array
    {
        $owner=sanitize_key((string)($input['owner_module']??''));
        $object=sanitize_text_field((string)($input['owner_object_id']??''));
        $source=LocaleValidator::canonicalize((string)($input['source_locale']??''));
        $target=LocaleValidator::canonicalize((string)($input['target_locale']??''));
        $hash=strtolower((string)($input['source_hash']??''));
        $sourceVersion=sanitize_text_field((string)($input['source_version']??''));
        if(''===$owner||''===$object||null===$source||null===$target||$source===$target||''===$sourceVersion||1!==preg_match('/^[a-f0-9]{64}$/D',$hash)){
            throw new InvalidArgumentException('Content translation relationship is invalid.');
        }
        $resourceUuid=$this->uuidOrNull($input['resource_uuid']??null);
        $unitUuid=$this->uuidOrNull($input['unit_uuid']??null);
        if(null!==$resourceUuid&&!$this->repo->find('resources',$resourceUuid)){throw new InvalidArgumentException('Content relationship resource does not exist.');}
        $unit=null;
        if(null!==$unitUuid){$unit=$this->repo->find('units',$unitUuid);if(!is_array($unit)){throw new InvalidArgumentException('Content relationship translation unit does not exist.');}}
        $urls=[];foreach(['translated_url','canonical_url'] as $field){$url=(string)($input[$field]??'');if(''!==$url){$this->assertPlatformUrl($url);$urls[$field]=esc_url_raw($url);}else{$urls[$field]=null;}}
        $status=sanitize_key((string)($input['publication_status']??'draft'));
        if(!in_array($status,['draft','review','approved','published','stale','retracted','retired'],true)){throw new InvalidArgumentException('Content translation publication status is invalid.');}
        $approvalRef=sanitize_text_field((string)($input['owner_approval_ref']??''));
        if('published'===$status){
            if(''===$approvalRef||!is_array($unit)||!in_array((string)$unit['status'],['approved','released'],true)||!hash_equals((string)$unit['source_hash'],$hash)){
                throw new InvalidArgumentException('Current approved unit and native-owner approval evidence are required before publication.');
            }
            $evidence=['owner_module'=>$owner,'owner_object_id'=>$object,'target_locale'=>$target,'approval_ref'=>$approvalRef,'source_version'=>$sourceVersion,'source_hash'=>$hash,'unit_uuid'=>$unitUuid];
            if(true!==apply_filters('slto_validate_owner_approval',false,$evidence)){throw new InvalidArgumentException('Native owner approval evidence could not be verified.');}
        }
        $data=['source_version'=>$sourceVersion,'source_hash'=>$hash,'resource_uuid'=>$resourceUuid,'unit_uuid'=>$unitUuid,
            'translated_url'=>$urls['translated_url'],'canonical_url'=>$urls['canonical_url'],'hreflang_code'=>$target,
            'publication_status'=>$status,'owner_approval_ref'=>$approvalRef?:null];
        return $this->tx->run(function() use ($owner,$object,$source,$target,$data,$input,$hash,$status): array {
            $existing=$this->repo->findContentLink($owner,$object,$target);
            $row=is_array($existing)?$this->repo->updateVersioned('content_links',(string)$existing['uuid'],(int)($input['row_version']??$existing['row_version']),$data)
                :$this->repo->insert('content_links',array_merge($data,['owner_module'=>$owner,'owner_object_id'=>$object,'source_locale'=>$source,'target_locale'=>$target,'row_version'=>1]));
            $this->audit->record('content_link',(string)$row['uuid'],'content_translation_link_registered','success',['owner_module'=>$owner,'target_locale'=>$target,'source_hash'=>$hash,'publication_status'=>$status]);
            return $row;
        });
    }

    private function uuidOrNull(mixed $value): ?string
    {
        $value=trim((string)$value);
        if(''===$value){return null;}
        if(1!==preg_match('/^[a-f0-9-]{36}$/D',$value)){throw new InvalidArgumentException('Content relationship UUID is invalid.');}
        return $value;
    }

    private function assertPlatformUrl(string $url): void
    {
        if(!wp_http_validate_url($url)||'https'!==strtolower((string)wp_parse_url($url,PHP_URL_SCHEME))){throw new InvalidArgumentException('Content translation URL is invalid.');}
        $targetHost=strtolower((string)wp_parse_url($url,PHP_URL_HOST));$homeHost=strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST));
        $targetPort=wp_parse_url($url,PHP_URL_PORT);$homePort=wp_parse_url(home_url('/'),PHP_URL_PORT);
        if(''===$targetHost||''===$homeHost||!hash_equals($homeHost,$targetHost)||$targetPort!==$homePort){throw new InvalidArgumentException('Content translation URL must remain on the canonical HTTPS origin.');}
    }
}
