<?php
declare(strict_types=1);
/** New 40 independent ICU style/skeleton parity behavioral tests, no external data. */
require __DIR__ . '/bootstrap.php';
use Sabri\Localization\Domain\Translation\MessageFormatValidator;
$cases = [
    ['S01','plain unstyled number',true,'Amount {n, number}','رقم {n, number}'],
    ['S02','currency style preserved',true,'Amount {n, number, currency}','رقم {n, number, currency}'],
    ['S03','percent style preserved',true,'Rate {n, number, percent}','شرح {n, number, percent}'],
    ['S04','integer style preserved',true,'People {n, number, integer}','افراد {n, number, integer}'],
    ['S05','date short preserved',true,'Date {d, date, short}','تاریخ {d, date, short}'],
    ['S06','date medium preserved',true,'Date {d, date, medium}','تاریخ {d, date, medium}'],
    ['S07','date long preserved',true,'Date {d, date, long}','تاریخ {d, date, long}'],
    ['S08','time short preserved',true,'At {t, time, short}','وقت {t, time, short}'],
    ['S09','time long preserved',true,'At {t, time, long}','وقت {t, time, long}'],
    ['S10','currency code skeleton preserved',true,'Cost {n, number, ::currency/PKR}','قیمت {n, number, ::currency/PKR}'],
    ['S11','USD skeleton preserved',true,'Cost {n, number, ::currency/USD}','قیمت {n, number, ::currency/USD}'],
    ['S12','unit skeleton preserved',true,'Measure {n, number, ::unit/meter}','لمبائی {n, number, ::unit/meter}'],
    ['S13','custom date skeleton preserved',true,'Date {d, date, yyyy-MM-dd}','تاریخ {d, date, yyyy-MM-dd}'],
    ['S14','styled time preserved',true,'Time {t, time, HH:mm}','وقت {t, time, HH:mm}'],
    ['S15','extra external whitespace around style accepted',true,'{n, number, currency}','{n, number,    currency  }'],
    ['S16','unchanged repeated styled placeholder',true,'{n, number, currency} then {n, number, currency}','{n, number, currency} پھر {n, number, currency}'],
    ['S17','two arguments distinct styles',true,'{a, number, currency} {b, number, percent}','{b, number, percent} اور {a, number, currency}'],
    ['S18','select branch nested stable styled format',true,'{s, select, yes {{n, number, currency} yes} other {no}}','{s, select, other {نہیں} yes {{n, number, currency} ہاں}}'],
    ['S19','plural branches nested stable styled format',true,'{k, plural, one {{n, number, currency} one} other {{n, number, currency} many}}','{k, plural, other {{n, number, currency} بہت} one {{n, number, currency} ایک}}'],
    ['S20','no-style simple date retained',true,'{d, date}','{d, date}'],
    ['S21','currency to percent rejected',false,'{n, number, currency}','{n, number, percent}'],
    ['S22','currency to integer rejected',false,'{n, number, currency}','{n, number, integer}'],
    ['S23','percent to currency rejected',false,'{n, number, percent}','{n, number, currency}'],
    ['S24','style added rejected',false,'{n, number}','{n, number, currency}'],
    ['S25','style removed rejected',false,'{n, number, currency}','{n, number}'],
    ['S26','date short to long rejected',false,'{d, date, short}','{d, date, long}'],
    ['S27','date medium to full rejected',false,'{d, date, medium}','{d, date, full}'],
    ['S28','time short to long rejected',false,'{t, time, short}','{t, time, long}'],
    ['S29','skeleton PKR to USD rejected',false,'{n, number, ::currency/PKR}','{n, number, ::currency/USD}'],
    ['S30','unit meter to kilometer rejected',false,'{n, number, ::unit/meter}','{n, number, ::unit/kilometer}'],
    ['S31','custom date year-day swap rejected',false,'{d, date, yyyy-MM-dd}','{d, date, dd-MM-yyyy}'],
    ['S32','source conflict repeated number styles rejected',false,'{n, number, currency} {n, number, percent}','{n, number, currency} {n, number, currency}'],
    ['S33','target conflict repeated number styles rejected',false,'{n, number, currency} {n, number, currency}','{n, number, percent} {n, number, currency}'],
    ['S34','nested style changed rejected',false,'{s, select, yes {{n, number, currency}} other {none}}','{s, select, other {نہیں} yes {{n, number, percent}}}'],
    ['S35','no comma before style rejected',false,'{n, number, currency}','{n, number currency}'],
    ['S36','empty explicit style rejected',false,'{n, number}','{n, number, }'],
    ['S37','comma with empty source style rejected',false,'{n, number, }','{n, number}'],
    ['S38','control-character in style rejected',false,'{n, number, currency}','{n, number, cur\nrency}'],
    ['S39','huge style rejected',false,'{n, number}','{n, number, aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa}'],
    ['S40','mismatched formatter type rejected',false,'{d, date, short}','{d, time, short}'],
];
if(count($cases)!==40){ throw new RuntimeException('Exactly 40 new style cases required.'); }
$fail=[];
foreach($cases as [$id,$name,$allowed,$source,$target]){
  $actual=true;
  try { MessageFormatValidator::assertEquivalent($source,$target); }
  catch(\InvalidArgumentException $e) { $actual=false; }
  $pass=$actual===$allowed;
  echo ($pass?'PASS ':'FAIL ').$id.' '.$name."\n";
  if(!$pass){$fail[]=$id;}
}
echo 'CF06 fresh ICU formatted style/skeleton: '.(40-count($fail)).'/40 PASS'."\n";
exit($fail===[]?0:1);
