<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Crea (o sovrascrive) la homepage con i blocchi della landing e le pagine legali segnaposto.
 * I testi tra [TODO ...] vanno sostituiti dal pannello con i contenuti reali.
 */
class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        $home = Page::query()->where('is_homepage', true)->first() ?? new Page(['slug' => 'home']);

        $home->fill([
            'title' => 'Home',
            'is_homepage' => true,
            'is_published' => true,
            'excerpt' => 'Parcheggio vicino all\'aeroporto di Bologna con navetta inclusa.',
            'meta_title' => 'Parcheggio aeroporto di Bologna con navetta inclusa',
            'meta_description' => 'Parcheggio a pochi minuti dall\'aeroporto Marconi di Bologna: prenoti online, navetta andata e ritorno inclusa, area recintata e sorvegliata.',
            'blocks' => self::landingBlocks(),
        ])->save();

        foreach ([
            'privacy' => 'Privacy',
            'cookie' => 'Cookie',
            'condizioni-del-servizio' => 'Condizioni del servizio',
        ] as $slug => $title) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'is_published' => true,
                'robots_index' => false,
                'blocks' => [[
                    'type' => 'text_image',
                    'data' => [
                        'content' => '<p>[TODO: testo della pagina "'.$title.'" da fornire a cura del gestore.]</p>',
                        'background' => 'carta',
                    ],
                ]],
            ]);
        }
    }

    /**
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public static function landingBlocks(): array
    {
        return [
            ['type' => 'hero', 'data' => [
                'heading' => 'Lasci l\'auto da noi, al volo ci pensiamo insieme.',
                'subheading' => 'Parcheggio a pochi minuti dall\'aeroporto Marconi. Prenoti online, ti accompagniamo al terminal in navetta e al ritorno ritrovi l\'auto dove l\'hai lasciata.',
                'show_sign' => true,
                'sign_title' => 'Aeroporto di Bologna',
                'sign_text' => '{minuti} minuti in navetta, inclusa nel prezzo',
                'show_booking_form' => true,
            ]],
            ['type' => 'features', 'data' => [
                'background' => 'terra',
                'anchor' => null,
                'items' => [
                    ['icon' => 'bus', 'title' => 'Navetta inclusa', 'description' => 'Andata e ritorno dal terminal, anche con i bagagli.'],
                    ['icon' => 'shield-check', 'title' => 'Area recintata e sorvegliata', 'description' => 'Videosorveglianza e personale presente {orari}.'],
                    ['icon' => 'moon', 'title' => 'Voli all\'alba o a notte fonda', 'description' => 'Ti aspettiamo anche se l\'aereo è in ritardo.'],
                    ['icon' => 'receipt', 'title' => 'Prezzo chiaro, deciso prima', 'description' => 'Paghi online o alla consegna dell\'auto, nessun extra a sorpresa.'],
                ],
            ]],
            ['type' => 'steps', 'data' => [
                'heading' => 'Dal parcheggio al gate, senza pensieri',
                'subheading' => 'Quattro passaggi, e il più faticoso è scegliere cosa mettere in valigia.',
                'background' => 'terra',
                'anchor' => 'come',
                'items' => [
                    ['title' => 'Prenoti online', 'description' => 'Scegli date e orari, vedi il prezzo e ricevi la conferma via email.'],
                    ['title' => 'Arrivi al parcheggio', 'description' => 'Ti accogliamo all\'ingresso e ti indichiamo dove lasciare l\'auto.'],
                    ['title' => 'Navetta al terminal', 'description' => 'In {minuti} minuti sei alle partenze, bagagli compresi.'],
                    ['title' => 'Al ritorno ti veniamo a prendere', 'description' => 'Ci chiami quando atterri e la navetta ti riporta all\'auto.'],
                ],
            ]],
            ['type' => 'pricing', 'data' => [
                'heading' => 'Tariffe semplici, per tutta la durata del viaggio',
                'text' => 'Il prezzo comprende posto auto e navetta andata e ritorno. Più resti, meno spendi al giorno.',
                'table_caption' => 'Posto scoperto, navetta inclusa',
                'button_label' => 'Calcola il prezzo per le tue date',
                'button_url' => '/#prenota',
                'background' => 'carta',
                'anchor' => 'tariffe',
            ]],
            ['type' => 'testimonials', 'data' => [
                'heading' => 'Chi è partito con noi',
                'show_rating' => true,
                'background' => 'terra',
                'anchor' => 'recensioni',
                'items' => [
                    ['quote' => '[TODO: recensione reale] Volo alle sei del mattino, navetta puntuale e auto ritrovata esattamente dove l\'avevo lasciata.', 'author' => '[TODO] Nome C.', 'role' => 'Recensione Google'],
                    ['quote' => '[TODO: recensione reale] Al ritorno il volo era in ritardo di due ore: ci hanno aspettato senza problemi.', 'author' => '[TODO] Nome C.', 'role' => 'Recensione Google'],
                    ['quote' => '[TODO: recensione reale] Prezzo chiaro fin dall\'inizio, nessuna sorpresa alla consegna delle chiavi.', 'author' => '[TODO] Nome C.', 'role' => 'Recensione Google'],
                ],
            ]],
            ['type' => 'location', 'data' => [
                'heading' => 'Dove siamo',
                'text' => 'A {minuti} minuti di navetta dal terminal dell\'aeroporto Marconi.',
                'directions' => [
                    ['term' => 'Dall\'autostrada', 'description' => '[TODO: indicazioni dall\'uscita autostradale]'],
                    ['term' => 'Dalla tangenziale', 'description' => '[TODO: indicazioni dall\'uscita della tangenziale]'],
                ],
                'show_hours' => true,
                'button_label' => 'Apri in Google Maps',
                'background' => 'terra',
                'anchor' => 'dove',
            ]],
            ['type' => 'faq', 'data' => [
                'heading' => 'Domande frequenti',
                'intro' => 'Non trovi la risposta? Chiamaci al {telefono}, rispondiamo volentieri.',
                'background' => 'carta',
                'anchor' => 'domande',
                'items' => [
                    ['question' => 'E se il mio volo arriva in ritardo?', 'answer' => 'Nessun problema: controlliamo gli arrivi e la navetta ti aspetta. Chiamaci quando atterri e veniamo a prenderti al terminal.'],
                    ['question' => 'Devo lasciare le chiavi dell\'auto?', 'answer' => '[TODO: specificare se le chiavi vanno lasciate al personale o se il cliente le tiene con sé.]'],
                    ['question' => 'Ogni quanto passa la navetta?', 'answer' => '[TODO: frequenza della navetta.] Il tragitto fino al terminal dura circa {minuti} minuti.'],
                    ['question' => 'Posso modificare o cancellare la prenotazione?', 'answer' => 'Sì, la cancellazione è gratuita fino a {ore_cancellazione} ore prima della partenza. [TODO: modalità di modifica della prenotazione.]'],
                ],
            ]],
            ['type' => 'cta', 'data' => [
                'heading' => 'Il posto è pronto. A te resta solo la valigia.',
                'button_label' => 'Prenota il posto',
                'button_url' => '/#prenota',
            ]],
        ];
    }
}
