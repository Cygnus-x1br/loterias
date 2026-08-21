import pandas as pd
import json
import hashlib
from datetime import datetime

# Ler o arquivo
df = pd.read_excel('docs/Lotofácil.xlsx')

def clean_money(val):
    if pd.isna(val) or val == '' or val == '-':
        return "0.00"
    if isinstance(val, (int, float)):
        return f"{float(val):.2f}"
    
    val_str = str(val).replace('R$', '').replace('.', '').replace(',', '.').strip()
    try:
        return f"{float(val_str):.2f}"
    except:
        return "0.00"

def clean_int(val):
    if pd.isna(val) or val == '' or val == '-':
        return 0
    try:
        return int(float(val))
    except:
        return 0

results = []

for index, row in df.iterrows():
    contest = clean_int(row['Concurso'])
    if contest == 0:
        continue
        
    date_raw = str(row['Data Sorteio'])
    try:
        dt = datetime.strptime(date_raw, '%d/%m/%Y')
        iso_date = dt.strftime('%Y-%m-%dT00:00:00.000000Z')
    except:
        iso_date = None

    # Extrair dezenas
    numbers = []
    for i in range(1, 16):
        num = clean_int(row[f'Bola{i}'])
        if num > 0:
            numbers.append(num)
    
    numbers.sort()
    # Generar Hash exato como no seeder
    hash_str = hashlib.sha256(json.dumps(numbers, separators=(',', ':')).encode('utf-8')).hexdigest()
    
    result = {
        "id": contest,
        "contest_number": contest,
        "draw_date": iso_date,
        "drawn_numbers": numbers,
        "drawn_numbers_hash": hash_str,
        "winners_15_hits": clean_int(row['Ganhadores 15 acertos']),
        "payout_15_hits": clean_money(row['Rateio 15 acertos']),
        "winners_14_hits": clean_int(row['Ganhadores 14 acertos']),
        "payout_14_hits": clean_money(row['Rateio 14 acertos']),
        "winners_13_hits": clean_int(row['Ganhadores 13 acertos']),
        "payout_13_hits": clean_money(row['Rateio 13 acertos']),
        "winners_12_hits": clean_int(row['Ganhadores 12 acertos']),
        "payout_12_hits": clean_money(row['Rateio 12 acertos']),
        "winners_11_hits": clean_int(row['Ganhadores 11 acertos']),
        "payout_11_hits": clean_money(row['Rateio 11 acertos'])
    }
    results.append(result)

# Salvar o JSON
output_path = 'database/seeders/historical_results.json'
with open(output_path, 'w', encoding='utf-8') as f:
    json.dump(results, f, ensure_ascii=False, indent=4)

print(f"Processamento concluído. {len(results)} concursos exportados para {output_path}")
