"""
categorias.py — Detecta categoria de produto fitness automaticamente pelo nome.
Usada pelos 3 coletores (ML, Magalu, Shopee) ao inserir em `ofertas`.
"""
import re
import unicodedata


def _norm(texto: str) -> str:
    texto = unicodedata.normalize('NFKD', texto.lower())
    return texto.encode('ascii', 'ignore').decode('ascii')


def detectar_categoria(nome: str) -> str:
    n = _norm(nome)
    if re.search(r'\b(whey|proteina|albumina|caseina|hipercalorico|massa|isolado|concentrado)\b', n):
        return 'proteinas'
    if re.search(r'\b(creatina|monohidratada)\b', n):
        return 'creatina'
    if re.search(r'\b(pre.?treino|pre.?workout|termogenico|energia|foco)\b', n):
        return 'pre_treino'
    if re.search(r'\b(bcaa|aminoacido|glutamina|arginina|taurina)\b', n):
        return 'aminoacidos'
    if re.search(r'\b(omega|vitamina|multivitaminico|zinco|magnesio|colageno|probiotico|imunidade)\b', n):
        return 'vitaminas'
    if re.search(r'\b(pasta amendoim|barra proteica|snack|wafer|biscoito proteico|granola)\b', n):
        return 'snacks'
    if re.search(r'\b(haltere|anilha|barra|kettlebell|elasit|faixa|corda|step|bola pilates|roda abdominal|caneleira|colete peso)\b', n):
        return 'equipamentos'
    if re.search(r'\b(esteira|bicicleta|spinning|eliptico|ergometrica)\b', n):
        return 'cardio'
    if re.search(r'\b(legging|short|bermuda|conjunto|camiseta|regata|top|calca|tenis|roupa academia|dry.?fit)\b', n):
        return 'roupas'
    if re.search(r'\b(coqueteleira|garrafa termica|shakeira|copo termico|squeeze)\b', n):
        return 'acessorios'
    if re.search(r'\b(luva|munhequeira|cinto|strap|joelheira|tornozeleira|mochila academia)\b', n):
        return 'acessorios'
    if re.search(r'\b(smartwatch|relogio esportivo|monitor cardiaco|balanca bioimpedancia)\b', n):
        return 'monitoramento'
    return 'outros'
