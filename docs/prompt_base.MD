# Prompt mestre — Sistema de geração, fechamento e análise combinatória da Lotofácil

## 1. Contexto geral do projeto

Estou desenvolvendo um sistema matemático e computacional para análise, geração, otimização e validação de jogos da Lotofácil.

A Lotofácil possui:

- universo total de 25 dezenas, numeradas de 1 a 25;
- sorteio de 15 dezenas distintas;
- apostas simples compostas por 15 dezenas;
- faixas de premiação para 11, 12, 13, 14 e 15 acertos;
- possibilidade de trabalhar com grupos-base maiores, de 15 a 25 dezenas;
- possibilidade de gerar fechamentos integrais ou reduzidos.

O sistema não deve afirmar que consegue prever o resultado do sorteio. Todas as dezenas devem ser tratadas como equiprováveis, salvo quando o usuário aplicar filtros ou pesos experimentais explicitamente identificados como heurísticos.

O objetivo do sistema é:

1. gerar combinações válidas;
2. construir fechamentos integrais e reduzidos;
3. medir cobertura combinatória;
4. encontrar conjuntos de apostas dentro de um orçamento;
5. verificar garantias matemáticas condicionais;
6. comparar diferentes métodos de otimização;
7. simular resultados;
8. gerar relatórios transparentes sobre cobertura, custo e limitações.

## 2. Regras matemáticas fundamentais

Use as seguintes definições:

- \(U = \{1,2,\ldots,25\}\): universo das dezenas;
- \(D\): conjunto sorteado, com \(|D|=15\);
- \(B_i\): uma aposta, com \(|B_i|=15\);
- \(S\): grupo-base escolhido pelo usuário;
- \(m = |S|\): quantidade de dezenas no grupo-base;
- \(\mathcal{B}\): conjunto de apostas geradas;
- \(t\): quantidade mínima de acertos desejada;
- \(C = |\mathcal{B}|\): quantidade total de apostas.

O número total de sorteios possíveis é:

\[
N_{\text{sorteios}} = \binom{25}{15} = 3.268.760
\]

A quantidade de apostas simples possíveis também é:

\[
N_{\text{apostas}} = \binom{25}{15} = 3.268.760
\]

A função de acertos entre uma aposta e um sorteio é:

\[
\operatorname{acertos}(B_i,D)=|B_i\cap D|
\]

Uma aposta \(B_i\) cobre o sorteio \(D\) para a faixa \(t\) quando:

\[
|B_i\cap D|\geq t
\]

A melhor pontuação obtida por um conjunto de apostas para um sorteio é:

\[
\operatorname{melhorPontuação}(D,\mathcal{B})
=

\max_{B_i\in\mathcal{B}} |B_i\cap D|
\]

A função de cobertura para uma faixa \(t\) é:

\[
\operatorname{cobre}_t(D,\mathcal{B})=
\begin{cases}
1, & \text{se existe } B_i\in\mathcal{B}\text{ tal que }|B_i\cap D|\geq t\\
0, & \text{caso contrário}
\end{cases}
\]

## 3. Requisitos gerais de implementação

Cada módulo deve:

1. possuir responsabilidade única;
2. receber parâmetros de entrada claramente definidos;
3. devolver resultados estruturados;
4. ser independente dos demais módulos sempre que possível;
5. não acessar diretamente a interface do usuário;
6. não misturar geração, otimização, simulação e apresentação;
7. possuir validações para entradas inválidas;
8. possuir testes unitários;
9. documentar a fórmula matemática utilizada;
10. informar limitações e hipóteses;
11. ser determinístico quando receber uma semente aleatória;
12. evitar apostas duplicadas;
13. utilizar representação eficiente de conjuntos;
14. permitir auditoria e reprodução dos resultados.

Ao implementar um item específico, preserve os nomes das entidades e conceitos definidos neste documento.

Quando eu solicitar:

> “Com base nas informações do item X, prepare um algoritmo que execute esse cálculo baseado nos parâmetros recuperados.”

ou:

> “Elabore o algoritmo para que meu sistema execute a tarefa proposta no item X.”

Você deverá trabalhar exclusivamente sobre o item solicitado, reutilizando as definições deste documento e sem alterar o escopo dos demais itens, salvo quando identificar uma dependência técnica necessária.

## 4. Modelo de dados comum

O sistema deve trabalhar, no mínimo, com as seguintes entidades.

### 4.1. Dezena

```text
 Dezena: valor: inteiro entre 1 e 25
```

### 4.2. Aposta

```text
 Aposta: dezenas: conjunto ordenado de 15 inteiros distintos identificador: string ou inteiro origem: método que gerou a aposta metadados: objeto opcional
```

### 4.3. Grupo-base

```text
 GrupoBase: dezenas: conjunto ordenado de m inteiros distintos tamanho: inteiro entre 15 e 25 origem: manual, estatística, aleatória ou otimizada
```

### 4.4. Fechamento

```text
 Fechamento: grupo_base: GrupoBase apostas: lista de Aposta quantidade_apostas: inteiro método: nome do algoritmo utilizado parâmetros: objeto garantias: lista de garantias condicionais métricas: objeto semente_aleatória: opcional
```

### 4.5. Resultado de validação

```text
 ResultadoValidacao: quantidade_cenários_analisados: inteiro menor_pontuação: inteiro maior_pontuação: inteiro distribuição_de_pontuações: objeto cobertura_por_faixa: objeto cenários_sem_cobertura: lista opcional garantia_confirmada: booleano condições_da_garantia: texto
```

# Módulos e algoritmos a serem implementados

## Item 1 — Validação de dezenas, apostas e parâmetros

Criar um módulo para validar:

- dezenas entre 1 e 25;
- ausência de duplicidades;
- apostas com exatamente 15 dezenas;
- grupos-base com tamanho entre 15 e 25;
- quantidade de apostas maior ou igual a 1;
- metas de acertos entre 11 e 15;
- orçamento válido;
- parâmetros de geração consistentes;
- sementes aleatórias válidas, quando fornecidas.

A validação deve rejeitar entradas inválidas antes da execução dos demais algoritmos.

### Regras

Para qualquer conjunto \(A\):

\[
A\subseteq U
\]

Uma aposta válida deve satisfazer:

\[
|B_i|=15
\]

Um grupo-base válido deve satisfazer:

\[
15\leq |S|\leq 25
\]

## Item 2 — Geração e enumeração de combinações

Criar um gerador de combinações que produza todos os subconjuntos de tamanho \(k\) de um conjunto de entrada.

A quantidade esperada de combinações é:

\[
\binom{n}{k}
=

\frac{n!}{k!(n-k)!}
\]

Para a Lotofácil:

\[
\binom{25}{15}=3.268.760
\]

O módulo deve permitir:

- gerar todas as apostas de 15 dezenas;
- gerar todas as apostas possíveis dentro de um grupo-base;
- gerar combinações de tamanho \(t\) para análise de cobertura;
- gerar combinações sob demanda, sem necessariamente carregar tudo na memória;
- interromper a geração quando atingir um limite configurado.

## Item 3 — Cálculo de probabilidades de uma aposta simples

Implementar o cálculo da probabilidade de uma aposta fixa de 15 dezenas obter exatamente \(k\) acertos.

Utilizar a distribuição hipergeométrica:

\[
P(X=k)
=

\frac{\binom{15}{k}\binom{10}{15-k}}
{\binom{25}{15}}
\]

O sistema deve calcular as probabilidades para:

- 11 acertos;
- 12 acertos;
- 13 acertos;
- 14 acertos;
- 15 acertos;
- pelo menos 11 acertos.

A probabilidade de pelo menos 11 acertos é:

\[
P(X\geq 11)
=

\sum_{k=11}^{15}
\frac{\binom{15}{k}\binom{10}{15-k}}
{\binom{25}{15}}
\]

O módulo deve devolver:

- probabilidade decimal;
- percentual;
- razão aproximada, como “1 em N”;
- quantidade de combinações favoráveis;
- fórmula utilizada.

## Item 4 — Cálculo de apostas equivalentes

Implementar o cálculo do número de apostas simples equivalentes para uma aposta com \(m\) dezenas.

A fórmula é:

\[
N_{\text{equivalentes}}=\binom{m}{15}
\]

Exemplos:

\[
\binom{16}{15}=16
\]

\[
\binom{17}{15}=136
\]

\[
\binom{18}{15}=816
\]

\[
\binom{19}{15}=3.876
\]

\[
\binom{20}{15}=15.504
\]

O módulo deve informar:

- quantidade de dezenas marcadas;
- quantidade de combinações simples equivalentes;
- custo estimado, se o preço da aposta for informado;
- observação de que a aposta maior representa várias combinações simples.

## Item 5 — Fechamento integral

Criar um algoritmo que, dado um grupo-base \(S\) com \(m\) dezenas, gere todas as combinações de 15 dezenas contidas nesse grupo.

A quantidade de apostas será:

\[
N_{\text{fechamento integral}}=\binom{m}{15}
\]

O fechamento integral deve garantir, sob a condição de que o sorteio esteja totalmente contido no grupo-base, pelo menos:

\[
\max(0, 30-m)
\]

acertos em alguma aposta.

Para \(m\) entre 15 e 25:

| Grupo-base | Apostas | Garantia mínima condicional |
| ---: | ---: | ---: |
| 15 | 1 | 15 |
| 16 | 16 | 14 |
| 17 | 136 | 13 |
| 18 | 816 | 12 |
| 19 | 3.876 | 11 |
| 20 | 15.504 | 10 |
| 21 | 54.264 | 9 |
| 22 | 170.544 | 8 |
| 23 | 490.314 | 7 |
| 24 | 1.307.504 | 6 |
| 25 | 3.268.760 | 5 |

A garantia deve ser descrita com precisão. Para grupos maiores que 19 dezenas, o fechamento integral não garante necessariamente 11 acertos apenas porque o sorteio está contido no grupo.

## Item 6 — Verificação de interseção entre grupo-base e sorteio

Criar um módulo que determine quantas dezenas sorteadas pertencem ao grupo-base.

A fórmula é:

\[
q=|S\cap D|
\]

Se o grupo-base contém \(q\) dezenas do sorteio e possui \(m\) dezenas, o maior número possível de acertos em uma aposta de 15 dezenas formada exclusivamente pelo grupo-base é:

\[
\operatorname{máximoAcertos}
=

\min(q,15)
\]

Quando \(q\geq 15\), o fechamento integral contém o sorteio completo e pode atingir 15 acertos.

Para um grupo-base de tamanho \(m\), contendo todas as 15 dezenas sorteadas, a melhor garantia estrutural é:

\[
15-\max(0,m-15)
=

30-m
\]

limitada ao intervalo de 0 a 15.

## Item 7 — Fechamento reduzido

Criar uma estrutura para selecionar apenas parte das combinações possíveis de um grupo-base.

Entrada mínima:

- grupo-base;
- quantidade máxima de apostas;
- meta de acertos;
- condição de cobertura;
- método de seleção;
- semente aleatória, se necessário.

Saída mínima:

- lista de apostas;
- quantidade de apostas;
- cobertura encontrada;
- garantia confirmada ou não confirmada;
- condições necessárias para a garantia;
- cenários não cobertos.

Nunca declarar uma garantia sem verificar todos os cenários relevantes ou sem declarar que a validação foi apenas estimada por simulação.

## Item 8 — Modelo de cobertura combinatória

Modelar o problema como uma estrutura de cobertura.

Para cada sorteio \(D\), uma aposta \(B_i\) cobre o cenário quando:

\[
|B_i\cap D|\geq t
\]

O sistema deve permitir definir:

- universo de cenários;
- conjunto de apostas candidatas;
- meta mínima de acertos;
- quantidade máxima de apostas;
- cobertura obrigatória;
- cobertura desejada;
- pesos para diferentes cenários.

A cobertura total de uma faixa \(t\) é:

\[
\operatorname{Cobertura}_t
=

\frac{
\sum_D \operatorname{cobre}_t(D,\mathcal{B})
}{
|\mathcal{D}|
}
\]

onde \(\mathcal{D}\) é o conjunto de cenários analisados.

## Item 9 — Problema de cobertura de conjuntos

Implementar o modelo de set covering.

Seja:

- \(U\): conjunto de cenários;
- \(A\): conjunto de apostas candidatas;
- \(x_i\in\{0,1\}\): variável que indica se a aposta \(i\) foi selecionada;
- \(a_{ij}=1\) se a aposta \(i\) cobre o cenário \(j\).

Modelo:

\[
\min \sum_i x_i
\]

sujeito a:

\[
\sum_i a_{ij}x_i\geq 1
\quad \forall j\in U
\]

\[
x_i\in\{0,1\}
\]

O objetivo é encontrar a menor quantidade de apostas que cubra todos os cenários definidos.

Também implementar a versão limitada por orçamento:

\[
\sum_i x_i\leq B
\]

maximizando:

\[
\max \sum_j w_j y_j
\]

onde \(y_j\) indica se o cenário \(j\) foi coberto.

## Item 10 — Programação inteira

Criar um modelo de programação inteira para otimizar fechamentos considerando simultaneamente:

- número máximo de apostas;
- cobertura mínima;
- cobertura de 11, 12, 13, 14 e 15 acertos;
- frequência mínima e máxima de cada dezena;
- quantidade de pares e ímpares;
- distribuição por faixas numéricas;
- limite de semelhança entre apostas;
- ausência de duplicidades;
- penalidades por concentração.

Uma função objetivo genérica pode ser:

\[
\max
\left(
w_{11}C_{11}
+w_{12}C_{12}
+w_{13}C_{13}
+w_{14}C_{14}
+w_{15}C_{15}
-\lambda B
-\mu R
-\nu Q
\right)
\]

onde:

- \(C_t\): cobertura da faixa \(t\);
- \(B\): quantidade de apostas;
- \(R\): redundância;
- \(Q\): penalidade por violação de equilíbrio;
- \(w_t,\lambda,\mu,\nu\): pesos configuráveis.

O algoritmo deve distinguir:

- solução ótima comprovada;
- solução viável;
- solução heurística;
- solução interrompida por limite de tempo.

## Item 11 — Heurística gulosa

Implementar uma heurística que selecione iterativamente a aposta com maior ganho marginal de cobertura.

Em cada etapa:

1. calcular os cenários ainda não cobertos;
2. avaliar cada aposta candidata;
3. escolher a aposta que cobre mais cenários novos;
4. aplicar penalidades de redundância e concentração;
5. adicionar a aposta selecionada;
6. atualizar os cenários restantes;
7. repetir até atingir o orçamento ou a cobertura desejada.

Função de seleção sugerida:

\[
\operatorname{pontuação}(B_i)
=

\operatorname{novosCenáriosCobertos}(B_i)
-\lambda\operatorname{redundância}(B_i)
-\mu\operatorname{concentração}(B_i)
\]

O módulo deve informar que a heurística gulosa não garante solução ótima.

## Item 12 — Busca local

Implementar uma busca local para melhorar uma solução existente.

Movimentos possíveis:

- trocar uma dezena de uma aposta;
- substituir uma aposta;
- trocar dezenas entre duas apostas;
- remover uma aposta redundante;
- inserir uma aposta que cubra cenários não cobertos;
- substituir uma aposta por outra com maior ganho marginal.

A função de avaliação pode ser:

\[
F(\mathcal{B})
=

w_{11}C_{11}
+w_{12}C_{12}
+w_{13}C_{13}
+w_{14}C_{14}
+w_{15}C_{15}
-\lambda|\mathcal{B}|
-\mu R
\]

O algoritmo deve parar quando:

- atingir um número máximo de iterações;
- não houver melhoria após \(n\) movimentos;
- atingir a cobertura desejada;
- atingir o limite de tempo.

## Item 13 — Simulated annealing

Implementar simulated annealing para explorar soluções combinatórias e escapar de ótimos locais.

Se uma solução candidata possuir variação de custo \(\Delta\), aceitar a nova solução conforme:

\[
P(\text{aceitar})
=

\begin{cases}
1, & \Delta\leq 0\\
e^{-\Delta/T}, & \Delta>0
\end{cases}
\]

Parâmetros configuráveis:

- temperatura inicial;
- temperatura mínima;
- fator de resfriamento;
- quantidade de iterações por temperatura;
- taxa de mutação;
- semente aleatória.

A temperatura pode ser atualizada por:

\[
T_{\text{novo}}=\alpha T_{\text{atual}}
\]

com:

\[
0<\alpha<1
\]

O sistema deve armazenar:

- melhor solução encontrada;
- histórico do custo;
- quantidade de movimentos aceitos;
- quantidade de movimentos rejeitados;
- parâmetros utilizados.

## Item 14 — Algoritmo genético

Implementar algoritmo genético para otimizar um conjunto de apostas.

Representação sugerida:

- cromossomo: fechamento completo;
- gene: uma aposta;
- alelo: uma dezena dentro da aposta.

Etapas:

1. gerar população inicial;
2. calcular a função de aptidão;
3. selecionar indivíduos;
4. realizar cruzamento;
5. realizar mutação;
6. corrigir indivíduos inválidos;
7. preservar os melhores indivíduos;
8. repetir por várias gerações.

A função de aptidão pode ser:

\[
\operatorname{fitness}(\mathcal{B})
=

w_{11}C_{11}
+w_{12}C_{12}
+w_{13}C_{13}
+w_{14}C_{14}
+w_{15}C_{15}
-\lambda R
-\mu V
\]

onde:

- \(C_t\): cobertura por faixa;
- \(R\): redundância;
- \(V\): quantidade de violações de restrições.

A mutação deve preservar:

- dezenas entre 1 e 25;
- ausência de duplicidades em cada aposta;
- exatamente 15 dezenas por aposta;
- ausência de apostas duplicadas, quando exigido.

## Item 15 — Programação por restrições

Criar um modelo que permita definir regras combinatórias como:

- cada dezena aparece entre \(a\) e \(b\) vezes;
- cada aposta possui quantidade mínima e máxima de ímpares;
- cada aposta possui quantidade mínima e máxima por faixa numérica;
- duas apostas não podem ter mais que \(r\) dezenas em comum;
- determinados pares devem aparecer em pelo menos uma aposta;
- determinadas trincas devem aparecer em pelo menos uma aposta;
- cada cenário deve possuir uma aposta com pelo menos \(t\) acertos;
- o fechamento deve possuir no máximo \(B\) apostas.

O modelo deve diferenciar:

- restrições obrigatórias;
- preferências;
- penalidades;
- metas de otimização.

## Item 16 — Geração de sistemas de roda

Implementar sistemas de roda, também chamados de wheel systems.

O sistema deve permitir:

- selecionar um grupo-base;
- definir quantidade de apostas;
- definir subconjuntos a serem cobertos;
- definir uma meta de acertos;
- gerar apostas distribuídas;
- calcular cobertura de pares;
- calcular cobertura de trincas;
- calcular cobertura de subconjuntos maiores;
- verificar garantias condicionais.

Para cada subconjunto \(T\) do grupo-base, verificar se existe alguma aposta \(B_i\) tal que:

\[
T\subseteq B_i
\]

Para garantia de pontuação, verificar:

\[
\forall D\in\mathcal{D},
\quad
\exists B_i\in\mathcal{B}
\text{ tal que }
|B_i\cap D|\geq t
\]

O sistema deve declarar explicitamente:

- qual subconjunto está sendo coberto;
- qual é o universo de cenários;
- qual é a meta de acertos;
- se a cobertura é total ou parcial;
- quais condições precisam ser verdadeiras;
- se a garantia foi demonstrada por enumeração exata ou estimada por amostragem.

## Item 17 — Controle de pares, ímpares e faixas numéricas

Criar filtros e métricas para avaliar a distribuição das apostas.

### Pares e ímpares

Para uma aposta \(B\):

\[
\operatorname{pares}(B)=|\{x\in B:x\bmod 2=0\}|
\]

\[
\operatorname{ímpares}(B)=15-\operatorname{pares}(B)
\]

### Faixas numéricas

Dividir as dezenas em:

- faixa 1: 1 a 5;
- faixa 2: 6 a 10;
- faixa 3: 11 a 15;
- faixa 4: 16 a 20;
- faixa 5: 21 a 25.

Para cada faixa \(F_j\):

\[
\operatorname{quantidade}(B,F_j)=|B\cap F_j|
\]

Esses filtros devem ser tratados como critérios de organização ou redução do espaço de busca, nunca como prova de aumento da probabilidade de uma combinação individual.

## Item 18 — Estatísticas históricas

Criar módulo opcional para importar e analisar resultados históricos.

Calcular:

- frequência de cada dezena;
- atraso de cada dezena;
- frequência em janelas móveis;
- pares e trincas recorrentes;
- distribuição de pares e ímpares;
- distribuição por faixas;
- repetição de dezenas entre concursos;
- média e variância das métricas.

O módulo deve deixar explícito:

- estatística histórica não prevê necessariamente o próximo sorteio;
- atrasos não tornam uma dezena “obrigatória”;
- frequências passadas podem ser utilizadas apenas como filtros, pesos ou critérios de preferência;
- qualquer modelo estatístico deve ser validado fora da amostra.

## Item 19 — Geração ponderada e filtros heurísticos

Permitir que o usuário atribua pesos às dezenas, pares ou padrões.

Se cada combinação \(B\) possuir um peso \(w(B)\), o algoritmo pode utilizar:

\[
P(B)
=

\frac{w(B)}
{\sum_{B'\in\mathcal{C}}w(B')}
\]

Essa distribuição deve ser identificada como uma distribuição artificial de geração, não como a probabilidade real do sorteio.

O módulo deve permitir:

- pesos por dezena;
- pesos por frequência histórica;
- penalidade para combinações repetidas;
- filtros de pares e ímpares;
- filtros por faixas;
- limites de concentração.

O sistema não deve apresentar uma combinação ponderada como matematicamente mais provável sem evidência estatística válida.

## Item 20 — Cálculo exato de cobertura de um fechamento

Para cada sorteio possível \(D\), calcular:

\[
s(D)=\max_{B_i\in\mathcal{B}}|B_i\cap D|
\]

Depois, calcular:

\[
C_t
=

\sum_{D}
\mathbf{1}[s(D)\geq t]
\]

A cobertura percentual é:

\[
P_t
=

\frac{C_t}{\binom{25}{15}}
\]

O módulo deve gerar:

- menor pontuação obtida;
- maior pontuação obtida;
- média da melhor pontuação;
- distribuição de pontuações;
- cobertura para 11, 12, 13, 14 e 15 acertos;
- quantidade de cenários sem cobertura;
- cenários responsáveis pela menor pontuação.

Quando o universo for completo, a validação deve ser exata.

## Item 21 — Verificação de garantia condicional

Implementar um verificador de garantias condicionais.

Exemplos de condições:

- pelo menos 15 dezenas sorteadas estão no grupo-base;
- pelo menos 14 dezenas sorteadas estão no grupo-base;
- pelo menos 13 dezenas sorteadas estão no grupo-base;
- determinado subconjunto obrigatório está presente;
- o sorteio pertence a uma classe específica de cenários.

Formalmente, para uma condição \(Q(D,S)\), verificar:

\[
\forall D:
Q(D,S)\Rightarrow
\left(
\exists B_i\in\mathcal{B}:
|B_i\cap D|\geq t
\right)
\]

O relatório deve apresentar a garantia no formato:

> Se a condição Q for verdadeira, então o fechamento garante pelo menos t acertos em alguma aposta.

Nunca usar frases genéricas como “garante 12 pontos” sem informar a condição.

## Item 22 — Probabilidade de pelo menos uma premiação em várias apostas

Para um conjunto de apostas \(\mathcal{B}\), calcular exatamente a probabilidade de pelo menos uma aposta obter uma determinada faixa.

Para cada sorteio \(D\), verificar:

\[
s(D)=\max_{B_i\in\mathcal{B}}|B_i\cap D|
\]

A probabilidade de pelo menos \(t\) acertos em alguma aposta é:

\[
P_{\geq t}
=

\frac{
|\{D:s(D)\geq t\}|
}{
\binom{25}{15}
}
\]

Não calcular essa probabilidade simplesmente multiplicando a probabilidade de uma aposta pelo número de apostas, pois as apostas podem ter sobreposição.

Para o prêmio máximo de 15 acertos, se todas as apostas forem distintas:

\[
P_{15}
=

\frac{|\mathcal{B}|}{\binom{25}{15}}
\]

desde que cada aposta represente uma combinação simples distinta.

## Item 23 — Simulação de sorteios

Criar um simulador capaz de gerar sorteios aleatórios uniformes de 15 dezenas entre 25.

A simulação deve permitir:

- quantidade de sorteios;
- semente aleatória;
- conjunto de apostas;
- cálculo da melhor pontuação;
- contagem de premiações;
- estimativa de retorno;
- intervalo de confiança;
- comparação entre métodos.

A probabilidade empírica é:

\[
\hat{p}=\frac{x}{n}
\]

onde:

- \(x\): quantidade de simulações com o evento;
- \(n\): quantidade total de simulações.

Um intervalo aproximado de 95% pode ser calculado por:

\[
\hat{p}\pm 1{,}96
\sqrt{\frac{\hat{p}(1-\hat{p})}{n}}
\]

A simulação não substitui a enumeração exata quando todos os \(3.268.760\) sorteios puderem ser analisados.

## Item 24 — Comparação de algoritmos

Criar um módulo para comparar diferentes fechamentos.

Comparar:

- quantidade de apostas;
- custo;
- cobertura de 11 acertos;
- cobertura de 12 acertos;
- cobertura de 13 acertos;
- cobertura de 14 acertos;
- cobertura de 15 acertos;
- menor pontuação;
- média da melhor pontuação;
- redundância;
- distribuição das dezenas;
- tempo de execução;
- uso de memória;
- se a garantia é exata ou estimada.

Toda comparação deve utilizar:

- o mesmo grupo-base;
- o mesmo orçamento;
- o mesmo universo de cenários;
- os mesmos critérios de avaliação;
- a mesma semente aleatória, quando aplicável.

## Item 25 — Cálculo de custo e orçamento

Criar um módulo que calcule o custo total de um fechamento.

Se \(p\) for o preço de uma aposta simples e \(C\) a quantidade de apostas:

\[
\operatorname{custoTotal}=C\cdot p
\]

O sistema deve permitir:

- limitar a quantidade de apostas;
- limitar o valor financeiro;
- calcular quantidade máxima de apostas;
- informar o custo por método;
- impedir geração acima do orçamento;
- distinguir custo teórico de custo real informado pelo usuário.

## Item 26 — Relatórios e transparência

Criar um gerador de relatório contendo:

- identificação do método;
- grupo-base;
- quantidade de apostas;
- custo;
- parâmetros;
- semente aleatória;
- cobertura;
- menor pontuação;
- maior pontuação;
- garantias;
- condições da garantia;
- cenários avaliados;
- método de validação;
- limitações;
- observações sobre aleatoriedade;
- indicação se o resultado foi exato, heurístico ou simulado.

O relatório deve evitar promessas de ganho ou previsões de resultados.

## Item 27 — Testes obrigatórios

Criar testes para:

1. validação de dezenas;
2. remoção de duplicidades;
3. geração de combinações;
4. cálculo de \(\binom{n}{k}\);
5. cálculo de probabilidades;
6. fechamento integral;
7. cálculo de interseção;
8. cobertura de cenários;
9. verificação de garantias;
10. detecção de apostas duplicadas;
11. controle de orçamento;
12. reprodução por semente aleatória;
13. comparação entre métodos;
14. casos limites com grupos de 15 e 25 dezenas;
15. entradas inválidas;
16. fechamento vazio;
17. fechamento com uma única aposta;
18. cobertura total de todos os sorteios no fechamento completo de 25 dezenas.

Para grupos pequenos, validar os resultados por enumeração manual ou fórmulas conhecidas.

## Item 28 — Requisitos de desempenho

O sistema deve:

- evitar gerar todas as combinações quando não for necessário;
- permitir geração sob demanda;
- usar operações eficientes de interseção;
- permitir processamento em lotes;
- permitir paralelização quando segura;
- informar progresso em operações longas;
- permitir cancelamento;
- limitar uso de memória;
- armazenar resultados intermediários quando apropriado;
- separar modo exato de modo aproximado.

O modo exato deve informar o tempo de processamento e a quantidade de cenários analisados.

## Item 29 — Classificação dos resultados

Todo resultado produzido pelo sistema deve ser classificado como uma destas categorias:

### Exato

Resultado obtido pela análise de todos os cenários relevantes ou por uma prova matemática.

### Ótimo comprovado

Solução cuja impossibilidade de melhoria foi demonstrada pelo método de otimização.

### Viável

Solução que atende às restrições, mas sem prova de que é a melhor.

### Heurístico

Solução encontrada por busca aproximada, gulosa, local, genética ou simulated annealing.

### Simulado

Resultado estimado por amostragem aleatória, sem enumeração completa.

Essa classificação deve aparecer nos relatórios e na interface.

## Item 30 — Regras de comunicação do sistema

O sistema deve sempre diferenciar:

1. probabilidade real de uma combinação;
2. quantidade de combinações compradas;
3. cobertura de cenários;
4. garantia condicional;
5. heurística de organização;
6. resultado histórico;
7. resultado simulado.

O sistema não deve:

- prometer acerto;
- afirmar que números atrasados estão mais prováveis;
- afirmar que números frequentes continuarão saindo;
- tratar filtros estatísticos como previsão;
- chamar uma solução heurística de garantia;
- omitir as condições de uma garantia;
- confundir custo maior com previsão melhor;
- usar resultados históricos como prova de causalidade.

# Formato esperado ao implementar qualquer item

Quando eu solicitar a implementação de um item específico, responda seguindo esta estrutura:

## 1. Objetivo do item

Explique claramente o que o módulo deve fazer.

## 2. Entradas

Liste todos os parâmetros necessários, seus tipos, limites e valores padrão.

## 3. Saídas

Defina a estrutura dos dados retornados.

## 4. Fundamentação matemática

Apresente as fórmulas utilizadas e explique cada variável.

## 5. Algoritmo

Descreva o passo a passo da execução.

## 6. Pseudocódigo

Forneça pseudocódigo independente da linguagem de programação.

## 7. Implementação

Produza o código na linguagem solicitada, mantendo o módulo desacoplado.

## 8. Validações

Liste as entradas inválidas, casos limites e verificações obrigatórias.

## 9. Testes

Crie testes unitários e casos de exemplo.

## 10. Complexidade

Informe a complexidade aproximada de tempo e memória.

## 11. Exemplo de uso

Forneça um exemplo completo de chamada e do resultado esperado.

## 12. Limitações

Explique claramente o que o algoritmo não pode garantir.

# Regra final

Não implemente todos os módulos de uma só vez quando eu solicitar apenas um item.

Ao receber uma solicitação como:

> “Elabore o algoritmo do item 9.”

Você deverá implementar somente o módulo correspondente ao Item 9, podendo criar interfaces auxiliares mínimas quando forem indispensáveis.

Mantenha compatibilidade com os conceitos, fórmulas, nomes e estruturas definidos neste documento.

O objetivo do projeto é construir um sistema de análise combinatória e otimização de apostas, e não um sistema de previsão garantida dos números sorteados.

Mensagem para o Agente

Adicionar

Ferramentas
