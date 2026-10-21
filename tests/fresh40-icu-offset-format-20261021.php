<?php
declare(strict_types=1);
/** CF06 FR-017/023 fresh 40 independent ICU offset / formatter adversarial fixtures. */
require __DIR__ . '/bootstrap.php';
use Sabri\Localization\Domain\Translation\MessageFormatValidator;
$cases = [
    ['R01','offset changed from 1 to 2','deny','{n, plural, offset:1 one {One} other {Many}}','{n, plural, offset:2 other {بہت} one {ایک}}'],
    ['R02','offset removed','deny','{n, plural, offset:1 one {One} other {Many}}','{n, plural, other {بہت} one {ایک}}'],
    ['R03','offset added','deny','{n, plural, one {One} other {Many}}','{n, plural, offset:1 other {بہت} one {ایک}}'],
    ['R04','offset 1 unchanged','allow','{n, plural, offset:1 one {One} other {Many}}','{n, plural, offset:1 other {بہت} one {ایک}}'],
    ['R05','offset zero unchanged','allow','{n, plural, offset:0 one {One} other {Many}}','{n, plural, offset:0 other {بہت} one {ایک}}'],
    ['R06','offset zero changed to one','deny','{n, plural, offset:0 one {One} other {Many}}','{n, plural, offset:1 other {بہت} one {ایک}}'],
    ['R07','equivalent leading-zero offset allowed','allow','{n, plural, offset:001 one {One} other {Many}}','{n, plural, offset:1 other {بہت} one {ایک}}'],
    ['R08','ordinal offset 1 to 2 blocked','deny','{n, selectordinal, offset:1 one {First} other {Others}}','{n, selectordinal, offset:2 other {دیگر} one {اول}}'],
    ['R09','ordinal offset 2 preserved','allow','{n, selectordinal, offset:2 one {First} other {Others}}','{n, selectordinal, offset:2 other {دیگر} one {اول}}'],
    ['R10','reordered plural categories with offset','allow','{n, plural, offset:3 one {One} other {Many}}','{n, plural, offset:3 other {بہت} one {ایک}}'],
    ['R11','duplicate offset rejected','deny','{n, plural, offset:1 one {One} other {Many}}','{n, plural, offset:1 offset:1 other {بہت} one {ایک}}'],
    ['R12','offset after category rejected','deny','{n, plural, offset:1 one {One} other {Many}}','{n, plural, one {A} offset:1 other {B}}'],
    ['R13','negative offset rejected','deny','{n, plural, offset:1 one {One} other {Many}}','{n, plural, offset:-1 other {بہت} one {ایک}}'],
    ['R14','fractional offset rejected','deny','{n, plural, offset:1 one {One} other {Many}}','{n, plural, offset:1.5 other {بہت} one {ایک}}'],
    ['R15','oversized offset rejected','deny','{n, plural, offset:1 one {One} other {Many}}','{n, plural, offset:1234567890 other {بہت} one {ایک}}'],
    ['R16','nonnumeric offset rejected','deny','{n, plural, offset:1 one {One} other {Many}}','{n, plural, offset:foo other {بہت} one {ایک}}'],
    ['R17','select type forbids offset','deny','{n, select, other {A}}','{n, select, offset:1 other {B}}'],
    ['R18','plural with missing other rejected','deny','{n, plural, offset:1 one {One} other {Many}}','{n, plural, offset:1 one {A}}'],
    ['R19','exact-number selector changed','deny','{n, plural, offset:1 =0 {Zero} other {More}}','{n, plural, offset:1 =1 {صفر} other {زیادہ}}'],
    ['R20','ordinal category changed','deny','{n, selectordinal, offset:1 one {First} other {Others}}','{n, selectordinal, offset:1 two {دوم} other {دیگر}}'],
    ['R21','bounded offset with ordinary spacing','allow','{n, plural, offset:12 one {One} other {Many}}','{n, plural, offset:12 other {بہت} one {ایک}}'],
    ['R22','plural hash token remains allowed','allow','{n, plural, offset:1 one {# day} other {# days}}','{n, plural, offset:1 other {# دن} one {# دن}}'],
    ['R23','nested plural offsets preserved','allow','{state, select, yes {{n, plural, offset:1 one {one} other {many}}} other {None}}','{state, select, other {نہیں} yes {{n, plural, offset:1 other {کئی} one {ایک}}}}'],
    ['R24','nested plural offset change blocked','deny','{state, select, yes {{n, plural, offset:1 one {one} other {many}}} other {None}}','{state, select, other {نہیں} yes {{n, plural, offset:2 other {کئی} one {ایک}}}}'],
    ['R25','plural argument rename rejected','deny','{n, plural, offset:1 one {One} other {Many}}','{m, plural, offset:1 other {بہت} one {ایک}}'],
    ['R26','source conflicting number/date rejected','deny','{x, number} {x, date}','{x, number} {x, number}'],
    ['R27','target conflicting number/date rejected','deny','{x, number} {x, number}','{x, number} {x, date}'],
    ['R28','same repeated number formatter allowed','allow','{x, number} {x, number}','{x, number} اور {x, number}'],
    ['R29','source conflicting date/number rejected','deny','{x, date} {x, number}','{x, date} {x, date}'],
    ['R30','source conflicting time/date rejected','deny','{x, time} {x, date}','{x, time} {x, time}'],
    ['R31','selector type conflict in source rejected','deny','{n, plural, one {One} other {Many}} {n, selectordinal, one {First} other {Others}}','{n, plural, one {One} other {Many}} {n, plural, one {One} other {Many}}'],
    ['R32','identical repeated plural allowed','allow','{n, plural, offset:1 one {One} other {Many}} {n, plural, offset:1 one {One} other {Many}}','{n, plural, offset:1 other {بہت} one {ایک}} {n, plural, offset:1 other {بہت} one {ایک}}'],
    ['R33','source repeated plural offset inconsistency rejected','deny','{n, plural, offset:1 one {One} other {Many}} {n, plural, offset:2 one {One} other {Many}}','{n, plural, offset:1 other {بہت} one {ایک}} {n, plural, offset:1 other {بہت} one {ایک}}'],
    ['R34','target repeated plural offset inconsistency rejected','deny','{n, plural, offset:1 one {One} other {Many}} {n, plural, offset:1 one {One} other {Many}}','{n, plural, offset:1 other {بہت} one {ایک}} {n, plural, offset:2 other {بہت} one {ایک}}'],
    ['R35','number to date rejected','deny','{x, number}','{x, date}'],
    ['R36','select versus plural type rejected','deny','{state, select, ready {Go} other {Wait}}','{state, plural, one {جائیں} other {رکیں}}'],
    ['R37','extra nested argument denied','deny','{state, select, ready {Ready} other {Wait}}','{state, select, ready {{secret} تیار} other {منتظر}}'],
    ['R38','same nested argument preserved','allow','{state, select, ready {{user} Ready} other {{user} Wait}}','{state, select, other {{user} منتظر} ready {{user} تیار}}'],
    ['R39','ordinal with same offsets and reorder allowed','allow','{n, selectordinal, offset:4 one {First} other {Others}}','{n, selectordinal, offset:4 other {دیگر} one {اول}}'],
    ['R40','ordinary ICU select without numeric offset remains allowed','allow','{state, select, ready {Go} other {Wait}}','{state, select, other {رکیں} ready {جائیں}}'],
];
if (count($cases)!==40) { throw new RuntimeException('Exactly 40 new ICU cases are required.'); }
$failures = [];
foreach ($cases as [$id,$label,$expected,$source,$target]) {
    $ok=true;
    try { MessageFormatValidator::assertEquivalent($source,$target); }
    catch (\InvalidArgumentException $e) { $ok=false; }
    $pass = ($ok===($expected==='allow'));
    echo ($pass?'PASS ':'FAIL ').$id.' '.$label."\n";
    if (!$pass) { $failures[]=$id; }
}
echo 'CF06 fresh ICU semantic offset/format: '.(40-count($failures))."/40 PASS\n";
exit($failures===[]?0:1);
