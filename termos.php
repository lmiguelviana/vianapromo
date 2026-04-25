<?php
require_once __DIR__ . '/app/helpers.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Uso e Política de Privacidade — CasaFit Ofertas by Rede de Ofertas Viana</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        h2 { font-size: 1rem; font-weight: 700; color: #1f2937; margin-top: 2rem; margin-bottom: .5rem; }
        p, li { font-size: .9rem; color: #4b5563; line-height: 1.75; }
        ul { list-style: disc; padding-left: 1.25rem; margin-top: .5rem; }
        li { margin-bottom: .25rem; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Header -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
    <div class="max-w-3xl mx-auto px-4 h-14 flex items-center gap-3">
        <a href="<?= BASE ?>/" class="flex items-center gap-2">
            <div class="w-7 h-7 bg-emerald-600 rounded-lg flex items-center justify-center">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <span class="font-bold text-sm text-gray-800">CasaFit <span class="text-emerald-600">Ofertas</span></span>
            <span class="block text-[9px] font-medium text-gray-400 tracking-wide leading-none">by Rede de Ofertas Viana</span>
        </a>
        <span class="text-gray-300 text-sm">/</span>
        <span class="text-sm text-gray-500">Termos e Privacidade</span>
    </div>
</header>

<main class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-extrabold text-gray-900 mb-1">Termos de Uso e Política de Privacidade</h1>
    <p class="text-xs text-gray-400 mb-8">Última atualização: <?= date('d/m/Y') ?></p>

    <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 mb-8">
        <p class="text-sm text-amber-800 font-medium">⚠️ Este site utiliza <strong>links de afiliado</strong>. Ao clicar em uma oferta e realizar uma compra, podemos receber uma pequena comissão — sem nenhum custo extra para você.</p>
    </div>

    <h2>1. O que é o CasaFit Ofertas?</h2>
    <p>O <strong>CasaFit Ofertas</strong> é um canal de divulgação de promoções e ofertas fitness. Não somos uma loja virtual e não vendemos, estocamos nem entregamos nenhum produto. Todas as ofertas divulgadas são de lojas parceiras (como Mercado Livre, Amazon, Shopee, entre outras), acessadas por meio de <strong>links de afiliado</strong>.</p>

    <h2>2. Links de Afiliado</h2>
    <p>Os links publicados neste site são links de afiliado. Isso significa que:</p>
    <ul>
        <li>Ao clicar e comprar, podemos receber uma comissão da plataforma parceira.</li>
        <li>O preço que você paga é <strong>o mesmo</strong> — a comissão é paga pela loja, não por você.</li>
        <li>Somos transparentes sobre isso e nos comprometemos a divulgar apenas ofertas que consideramos de qualidade.</li>
    </ul>

    <h2>3. Preços e Disponibilidade</h2>
    <p>Os preços e a disponibilidade dos produtos são gerenciados exclusivamente pelas lojas parceiras e podem mudar sem aviso prévio. O CasaFit Ofertas não se responsabiliza por:</p>
    <ul>
        <li>Variações de preço após a publicação da oferta.</li>
        <li>Indisponibilidade de estoque.</li>
        <li>Qualidade, entrega ou suporte pós-venda dos produtos — isso é de responsabilidade da loja onde a compra foi realizada.</li>
    </ul>

    <h2>4. Responsabilidade</h2>
    <p>O CasaFit Ofertas atua apenas como intermediário de divulgação. Em caso de problemas com um produto ou pedido, o contato deve ser feito diretamente com a loja onde a compra foi concluída.</p>

    <h2>5. Privacidade e Dados</h2>
    <p>Este site não coleta dados pessoais dos visitantes. Não exigimos cadastro para acessar as ofertas. Não utilizamos cookies de rastreamento de terceiros.</p>
    <p>Os links de afiliado podem direcionar para plataformas externas com suas próprias políticas de privacidade, sobre as quais não temos controle.</p>

    <h2>6. Contato</h2>
    <p>Dúvidas, sugestões ou solicitações podem ser enviadas pelo nosso Instagram:</p>
    <p class="mt-2">
        <a href="https://www.instagram.com/casafit_ofertas/" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1.5 text-emerald-700 font-semibold hover:underline">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
            </svg>
            @casafit_ofertas
        </a>
    </p>

    <div class="mt-10 pt-6 border-t border-gray-200">
        <a href="<?= BASE ?>/" class="inline-flex items-center gap-2 text-sm text-emerald-700 font-semibold hover:underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Voltar para as ofertas
        </a>
    </div>
</main>

<footer class="bg-emerald-700 mt-12">
    <div class="max-w-3xl mx-auto px-4 py-5 text-center">
        <span class="text-emerald-200 text-xs">© <?= date('Y') ?> CasaFit Ofertas by Rede de Ofertas Viana — Todos os direitos reservados</span>
    </div>
</footer>

</body>
</html>
