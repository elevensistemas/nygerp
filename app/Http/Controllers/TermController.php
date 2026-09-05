<?php

namespace App\Http\Controllers;

use App\Models\Term;
use App\Services\TermsPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TermController extends Controller
{
    public function edit(): View
    {
        if (! Schema::hasTable('terms')) {
            $term = new Term(['slug' => 'terms-of-use', 'content' => 'Redactá los términos aquí.']);
        } else {
            $term = Term::where('slug', 'terms-of-use')->firstOrFail();
        }

        return view('configuration.terms', [
            'term' => $term,
        ]);
    }

    public function update(Request $request, TermsPdfGenerator $pdfGenerator)
    {
        if (! Schema::hasTable('terms')) {
            return redirect()->route('terms.edit')->withErrors('La tabla de términos aún no fue creada.');
        }

        $term = Term::where('slug', 'terms-of-use')->firstOrFail();

        $data = $request->validate([
            'content' => 'required|string',
        ]);

        $term->update(['content' => $data['content']]);
        $pdfGenerator->generate($term->content);

        return redirect()->route('terms.edit')->with('ok', 'Términos actualizados');
    }

    public function view(): View
    {
        $term = Schema::hasTable('terms')
            ? Term::where('slug', 'terms-of-use')->first()
            : new Term(['slug' => 'terms-of-use', 'content' => 'Aquí todavía no se definieron los términos.']);

        return view('terms.view', compact('term'));
    }
}


