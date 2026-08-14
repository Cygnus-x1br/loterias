# Métodos matemáticos para fechamentos da Lotofácil

A Lotofácil pode ser modelada como uma seleção aleatória de **15 dezenas entre 25**, enquanto o apostador escolhe de **15 a 20 dezenas**, conforme as regras da CAIXA. Os prêmios ocorrem com 11, 12, 13, 14 ou 15 acertos. [0]

O ponto fundamental é:

> Nenhum método consegue prever as dezenas sorteadas. Os fechamentos apenas organizam várias apostas para aumentar a cobertura de combinações ou oferecer garantias condicionais.

## 1\. Probabilidade exata de uma aposta simples

O número total de resultados possíveis é:

```math
\binom{25}{15}=3.268.760
```

Para uma aposta fixa de 15 dezenas, a probabilidade de acertar exatamente (k) números é dada pela distribuição hipergeométrica:

$$
[
P(X=k)=
\\frac{\\binom{15}{k}\\binom{10}{15-k}}
{\\binom{25}{15}}
]
$$

A primeira combinação representa as dezenas acertadas dentro da aposta; a segunda representa as dezenas sorteadas que estão fora dela.

| Acertos | Combinações favoráveis | Probabilidade aproximada | Chance aproximada |
| ------: | ---------------------: | -----------------------: | ----------------: |
| 11 | 286.650 | 8,768% | 1 em 11,4 |
| 12 | 54.600 | 1,670% | 1 em 59,9 |
| 13 | 4.725 | 0,1445% | 1 em 692 |
| 14 | 150 | 0,00459% | 1 em 21.792 |
| 15 | 1 | 0,0000306% | 1 em 3.268.760 |

A probabilidade de ganhar **qualquer prêmio**, isto é, fazer pelo menos 11 acertos, é:

$$P(X\\geq 11)=\frac{286.650+54.600+4.725+150+1}{3.268.760}$$

$$P(X\\geq 11)\\approx 10,59%$$

Ou seja, aproximadamente **1 chance em 9,44** de obter alguma faixa de premiação em uma aposta simples, considerando a estrutura matemática acima.

As probabilidades oficiais e os valores de premiação devem ser conferidos diretamente na página da Lotofácil da CAIXA, pois regras e valores podem ser atualizados. [0]

***

# 2\. Fechamento integral

O fechamento integral consiste em escolher um grupo maior de dezenas e registrar **todas as combinações possíveis de 15 dezenas dentro desse grupo**.

Se o grupo tiver (v) dezenas, o número de apostas será:

$$B=\\binom{v}{15}$$

| Grupo escolhido | Número de apostas de 15 |
| --------------: | ----------------------: |
| 15 dezenas | 1 |
| 16 dezenas | 16 |
| 17 dezenas | 136 |
| 18 dezenas | 816 |
| 19 dezenas | 3.876 |
| 20 dezenas | 15.504 |
| 21 dezenas | 54.264 |
| 22 dezenas | 170.544 |
| 23 dezenas | 490.314 |
| 24 dezenas | 1.307.504 |
| 25 dezenas | 3.268.760 |

## Garantia estrutural do fechamento integral

Se o sorteio estiver contido no grupo escolhido, o fechamento integral produz uma garantia mínima:

* Grupo de 16 dezenas: garante pelo menos 14 acertos em uma das apostas;
* Grupo de 17 dezenas: garante pelo menos 13 acertos;
* Grupo de 18 dezenas: garante pelo menos 12 acertos;
* Grupo de 19 dezenas: garante pelo menos 11 acertos;
* Grupo de 20 dezenas: também garante pelo menos 11 acertos, desde que as 15 sorteadas estejam dentro das 20 escolhidas.

A razão é simples: se o grupo contém todas as 15 dezenas sorteadas, alguma aposta de 15 dezenas terá a maior interseção possível com o resultado.

Para um grupo de 17 dezenas, por exemplo, cada aposta deixa duas dezenas do grupo de fora. Como o sorteio contém 15 dezenas, é possível encontrar uma aposta que exclua duas dezenas não sorteadas, obtendo pelo menos:

$$15-2=13$$

acertos.

## Limitação

A garantia depende da hipótese de que o resultado sorteado esteja contido no grupo escolhido. Se uma ou mais dezenas sorteadas estiverem fora do grupo, a garantia pode desaparecer.

Portanto:

* o fechamento integral tem garantia combinatória;
* a escolha do grupo de dezenas não tem garantia preditiva;
* aumentar o grupo também aumenta exponencialmente o custo.

***

# 3\. Fechamento reduzido

No fechamento reduzido, escolhe-se um grupo de (v) dezenas, mas registra-se apenas uma parte das combinações de 15.

Exemplo:

* grupo-base: 18 dezenas;
* fechamento integral: 816 apostas;
* fechamento reduzido: 100 apostas.

A redução busca manter uma determinada cobertura, mas elimina muitas combinações.

Um fechamento reduzido deve ser descrito com três elementos:

1. **Grupo-base:** quantidade de dezenas disponíveis;
2. **Quantidade de apostas:** número de combinações geradas;
3. **Garantia:** condição matemática prometida.

Exemplo de especificação correta:

> Com 18 dezenas-base e 100 apostas, se as 15 dezenas sorteadas estiverem contidas nas 18 escolhidas, o sistema garante pelo menos 12 acertos em uma das apostas.

Sem essa condição, a afirmação “garante 12 pontos” é incompleta ou enganosa.

## Garantia condicional

As garantias normalmente assumem algo parecido com:

$$\|D\\cap S\|\\geq q$$

onde:

* $D$ é o conjunto sorteado de 15 dezenas;
* $S$ é o grupo-base escolhido;
* $q$ é o número mínimo de dezenas sorteadas presentes no grupo.

Uma garantia mais realista pode ser:

> Se pelo menos 14 das 15 dezenas sorteadas estiverem entre as 18 dezenas-base, o fechamento garante ao menos 11 acertos.

O sistema deve verificar essa propriedade por enumeração de todos os sorteios possíveis, e não apenas por simulação.

***

# 4\. Sistemas de cobertura combinatória

A teoria de **covering designs** trata da construção de conjuntos de blocos que cobrem todos os subconjuntos relevantes.

Na formulação tradicional, um covering design (C(v,k,t)) é uma coleção de blocos de tamanho (k), escolhidos entre (v) elementos, tal que todo subconjunto de tamanho (t) aparece em pelo menos um bloco. [1]

Para loterias, utiliza-se uma formulação relacionada, na qual o objetivo é garantir que, para cada possível sorteio, exista pelo menos uma aposta com determinada quantidade mínima de acertos.

Uma notação útil é:

$$L(n,k,p,t)$$

em que:

* $n$: quantidade total de dezenas disponíveis;
* $k$: tamanho de cada aposta;
* $p$: quantidade de dezenas sorteadas;
* $t$: quantidade mínima de acertos desejada.

Para a Lotofácil:

$$L(25,15,15,t)$$

Exemplos:

* $L(25,15,15,11)$: cobrir todos os sorteios possíveis com pelo menos uma aposta de 11 acertos;
* $L(25,15,15,12)$: cobrir todos os sorteios possíveis com pelo menos uma aposta de 12 acertos;
* $L(25,15,15,14)$: cobrir todos os sorteios possíveis com pelo menos uma aposta de 14 acertos.

Esse problema pode ser muito difícil computacionalmente. Pesquisas recentes utilizam programação por restrições para calcular projetos de loteria mínimos, tratando as apostas como blocos de um hipergrafo combinatório. [2]

## Diferença entre cobertura e previsão

Um sistema de cobertura responde:

> Quantas apostas são necessárias para cobrir determinados padrões de sorteio?

Ele não responde:

> Quais dezenas serão sorteadas?

Essa distinção é essencial.

***

# 5\. Wheel systems ou sistemas de roda

O **wheel system**, também chamado de sistema de roda ou fechamento em roda, é uma forma prática de fechamento reduzido.

O procedimento geral é:

1. selecionar um conjunto-base;
2. gerar diversas apostas de 15 dezenas;
3. distribuir as dezenas entre os bilhetes;
4. tentar maximizar a cobertura de subconjuntos;
5. impor uma garantia mínima, quando possível.

Há diferentes objetivos possíveis:

### Roda de cobertura de pares

Maximiza a presença de pares de dezenas em alguma aposta.

Pode ser útil para obter maior distribuição, mas não garante diretamente 11, 12 ou 13 acertos.

### Roda de trincas ou subconjuntos maiores

Tenta garantir que determinadas trincas, quartetos ou subconjuntos apareçam juntos em pelo menos uma aposta.

Quanto maior o subconjunto coberto, maior tende a ser a quantidade necessária de apostas.

### Roda com garantia de pontuação

O objetivo é garantir:

$$\\max\_i \|D\\cap B\_i\|\\geq t$$

para todo sorteio (D) dentro de uma classe especificada, onde:

* $B\_i$ é uma aposta;
* $D$ é um sorteio possível;
* $t$ é a pontuação mínima.

Os sistemas de roda são uma aplicação direta de problemas de cobertura combinatória. A literatura matemática trata explicitamente a relação entre lottery wheels e covering designs. [3]

***

# 6\. Formulação como problema de cobertura de conjuntos

A forma mais direta de construir um fechamento é transformar o problema em **set covering**.

Suponha que:

* $U$ seja o conjunto de cenários que se deseja cobrir;
* cada possível aposta $b$ cubra determinados cenários;
* $x\_b\\in{0,1}$ indique se a aposta será selecionada.

O modelo básico é:

$$ \\min \\sum\_b x\_b $$

sujeito a:

$$ \\sum\_{b:,b\\text{ cobre }u} x\_b\\geq 1\\quad \\forall u\\in U $$

$$ x\_b\\in{0,1} $$

O objetivo é minimizar o número de apostas mantendo a cobertura desejada.

## Exemplo de cenário

Pode-se definir que um cenário (u) seja um sorteio de 15 dezenas e que uma aposta cubra (u) se fizer pelo menos 11 acertos:

$$ \|u\\cap b\|\\geq 11 $$

Assim, o modelo procura o menor conjunto de apostas capaz de garantir 11 acertos em todos os sorteios considerados.

Pesquisas sobre o problema de loteria usam exatamente essa formulação de cobertura de conjuntos e métodos heurísticos para encontrar soluções em instâncias difíceis. [4]

## Variação com orçamento

Também é possível inverter o problema:

$$ \\max \\sum\_{u\\in U} w\_u y\_u $$

sujeito a:

$$ y\_u\\leq \\sum\_{b:,b\\text{ cobre }u}x\_b $$

$$ \\sum\_b x\_b\\leq B $$

onde:

* $B$ é o orçamento de apostas;
* $w\_u$ é o peso de um cenário;
* $y\_u$ indica se o cenário foi coberto.

Esse modelo não garante todos os sorteios, mas maximiza a cobertura com um número limitado de apostas.

***

# 7\. Programação inteira

A **programação inteira** é adequada quando o sistema precisa obedecer a várias restrições simultaneamente.

Exemplos:

* limitar a quantidade de apostas;
* evitar bilhetes duplicados;
* distribuir a frequência de cada dezena;
* controlar pares e ímpares;
* equilibrar as faixas 1–5, 6–10, 11–15, 16–20 e 21–25;
* maximizar a cobertura de determinados subconjuntos;
* evitar que duas apostas sejam muito semelhantes;
* garantir cobertura mínima de cenários.

Uma função objetivo possível é:

$$ \\max \\left(\\alpha \\cdot \\text{cobertura}-\\beta \cdot\\text{duplicidade}-\\gamma \\cdot \\text{concentração}\\right) $$

Os coeficientes $\\alpha,\\beta,\\gamma$ representam as prioridades do sistema.

## Vantagem

A programação inteira pode fornecer uma solução ótima para uma instância pequena ou uma solução com limite matemático conhecido.

## Limitação

O problema cresce rapidamente. Existem:

$$ \\binom{25}{15}=3.268.760 $$

apostas possíveis, além de milhões de cenários e restrições possíveis. Por isso, em muitos casos, usa-se uma combinação de:

* geração de candidatos;
* redução do espaço de busca;
* programação inteira;
* heurísticas;
* validação por enumeração ou amostragem.

A programação inteira é uma técnica geral de otimização discreta, na qual as variáveis precisam assumir valores inteiros, sendo o set covering um exemplo clássico. [5]

***

# 8\. Busca local

A busca local começa com um conjunto inicial de apostas e tenta melhorá-lo por pequenas alterações.

Exemplos de movimentos:

* trocar uma dezena por outra em um bilhete;
* substituir uma aposta inteira;
* adicionar ou remover uma dezena de uma combinação-base;
* trocar dezenas entre dois bilhetes;
* eliminar uma aposta redundante;
* inserir a aposta que cobre mais cenários ainda descobertos.

Uma função de avaliação pode ser:

$$ F= w\_{11}C\_{11}+w\_{12}C\_{12}+w\_{13}C\_{13}+w\_{14}C\_{14}+w\_{15}C\_{15}-\\lambda B$$

em que:

* $C\_t$: quantidade de cenários cobertos com pelo menos (t) acertos;
* $B$: número de apostas;
* $w\_t$: importância atribuída a cada faixa;
* $\\lambda$: penalização pelo custo.

A busca local é simples de implementar, mas pode ficar presa em ótimos locais.

***

# 9\. Simulated annealing

O **simulated annealing** aceita ocasionalmente uma solução pior para escapar de ótimos locais.

Se uma mudança causar variação de custo (\\Delta), ela pode ser aceita com probabilidade:

$$ P(\\text{aceitar})=\\begin{cases}1, & \\Delta\\leq 0\\e^{-\\Delta/T}, &\\Delta>0\\end{cases} $$

onde $T$ é a “temperatura” da busca.

No início:

* $T$ é maior;
* mudanças piores podem ser aceitas;
* a exploração é ampla.

No final:

* $T$ diminui;
* o algoritmo se torna mais conservador;
* a solução tende a estabilizar.

O método foi desenvolvido para problemas de otimização combinatória com muitos ótimos locais e é frequentemente combinado com outras técnicas. [6]

Na Lotofácil, o simulated annealing pode procurar um conjunto de apostas que:

* maximize a cobertura;
* minimize o número de bilhetes;
* respeite regras de equilíbrio;
* reduza a redundância entre apostas.

Ele não aumenta a probabilidade de uma aposta individual. Apenas pode produzir uma distribuição mais eficiente das apostas dentro do orçamento.

***

# 10\. Algoritmos genéticos

Um algoritmo genético representa uma solução como um “cromossomo”.

Por exemplo:

* cada gene representa uma aposta;
* cada aposta contém 15 dezenas;
* o cromossomo completo representa o fechamento inteiro.

O processo costuma ser:

1. gerar uma população inicial de fechamentos;
2. calcular a função de avaliação;
3. selecionar os melhores;
4. cruzar soluções;
5. aplicar mutações;
6. repetir por várias gerações.

Uma mutação pode:

* trocar uma dezena;
* substituir uma aposta;
* trocar partes de duas apostas;
* incluir uma combinação ainda não coberta.

A função de avaliação pode considerar:

* cobertura de 11 acertos;
* cobertura de 12 acertos;
* cobertura de 13 ou mais;
* quantidade de apostas;
* diversidade dos bilhetes;
* restrições de equilíbrio.

Há literatura específica sobre a combinação de algoritmos genéticos e simulated annealing para construir projetos de loteria. [7]

## Cuidados

Algoritmos genéticos podem encontrar uma solução muito boa para a função de avaliação definida, mas não necessariamente uma solução ótima.

Além disso, se a função de avaliação for baseada em padrões históricos, o algoritmo pode apenas aprender ruído do passado.

***

# 11\. Heurística gulosa

A heurística gulosa seleciona, a cada etapa, a aposta que cobre o maior número de cenários ainda não cobertos.

Pseudocódigo conceitual:

```text
cenarios_restantes = todos os cenários desejados
apostas = []

enquanto houver orçamento:
    escolher a aposta que cobre mais cenários restantes
    adicionar a aposta
    remover os cenários cobertos
```

Pode-se melhorar o critério incluindo penalizações:

```text
pontuação =
    cenários_novos_cobertos
    - penalidade_por_redundância
    - penalidade_por_concentração
```

É rápido e útil para gerar uma solução inicial. Porém, uma escolha localmente boa pode prejudicar as escolhas posteriores.

***

# 12\. Programação por restrições

A programação por restrições é particularmente adequada para exigências do tipo:

* cada dezena deve aparecer entre (a) e (b) vezes;
* cada aposta deve ter entre 6 e 9 ímpares;
* nenhuma dupla de apostas pode ter mais de 14 dezenas em comum;
* todo subconjunto relevante deve ser coberto;
* deve existir pelo menos uma aposta com determinada interseção para cada cenário.

A literatura recente utiliza modelos de restrições para encontrar o número mínimo de apostas em projetos de loteria e problemas equivalentes de hipergrafos. [2]

Esse método é interessante quando o sistema precisa oferecer uma explicação formal:

> “A solução satisfaz todas as restrições especificadas.”

***

# 13\. Métodos estatísticos: o que faz sentido

## Frequência histórica

Pode-se calcular:

* frequência de cada dezena;
* atraso desde o último sorteio;
* frequência em janelas recentes;
* pares e trincas mais frequentes;
* distribuição de pares e ímpares;
* distribuição por faixas numéricas.

Essas estatísticas podem servir como:

* filtros de geração;
* critérios de diversificação;
* parâmetros de preferência do usuário;
* mecanismos para evitar jogos idênticos.

Mas não demonstram que uma dezena esteja “mais próxima” de ser sorteada.

## Números quentes e frios

Se os sorteios forem independentes e o mecanismo for justo, o fato de uma dezena ter aparecido muitas vezes não reduz nem aumenta matematicamente sua probabilidade no próximo sorteio.

A crença de que uma dezena está “devendo” ocorrer por não ter acontecido recentemente é conhecida como falácia do jogador. Estudos sobre comportamento em loterias documentam esse tipo de erro de raciocínio. [8]

## Regressão, machine learning e redes neurais

Podem encontrar padrões no histórico, mas isso não implica capacidade preditiva real.

Para avaliar qualquer modelo, é necessário usar:

* separação temporal entre treinamento e teste;
* validação fora da amostra;
* comparação com apostas aleatórias;
* testes de significância;
* correção para múltiplas tentativas;
* análise de estabilidade em diferentes períodos.

Se um modelo foi testado em milhares de combinações e apenas o melhor resultado foi apresentado, há grande risco de **overfitting**.

## Equilíbrio entre pares e ímpares

Filtros como “7 pares e 8 ímpares” podem ser úteis para reduzir o espaço de busca, mas não aumentam a probabilidade de uma combinação específica.

Eles podem, no máximo:

* eliminar padrões considerados indesejados;
* reduzir jogos muito concentrados;
* tornar o conjunto de apostas mais diversificado.

***

# 14\. Critério importante: cobertura versus probabilidade

Considere (b) apostas distintas de 15 dezenas.

Para o prêmio máximo de 15 acertos, a probabilidade é exatamente:

$$ P(15\\text{ acertos em alguma aposta})=\\frac{b}{3.268.760} $$

desde que as apostas sejam diferentes.

Portanto, 100 apostas distintas têm:

$$ \\frac{100}{3.268.760} \\approx 0,003059% $$

ou aproximadamente:

$$ 1 \\text{ em } 32.688 $$

para acertar os 15 números.

Nenhuma organização especial altera essa probabilidade para o prêmio máximo quando se considera apenas a quantidade de apostas distintas. O que o fechamento pode alterar é:

* a distribuição dos acertos;
* a probabilidade de obter pelo menos 11 acertos;
* a existência de garantias condicionais;
* a concentração ou dispersão dos resultados entre os bilhetes.

***

# 15\. Sobre apostas com 16 a 20 dezenas

Uma aposta com mais de 15 dezenas equivale, matematicamente, a várias apostas simples de 15 dezenas.

O número de apostas equivalentes é:

$$ \\binom{m}{15} $$

para uma aposta de $m$ dezenas.

Assim:

| Dezenas marcadas | Apostas equivalentes |
| ---------------: | -------------------: |
| 15 | 1 |
| 16 | 16 |
| 17 | 136 |
| 18 | 816 |
| 19 | 3.876 |
| 20 | 15.504 |

A vantagem é a praticidade: o apostador não precisa preencher todas as combinações manualmente.

A desvantagem é que o custo cresce de forma combinatória. A aposta com 20 dezenas equivale a milhares de apostas simples, não a uma “previsão melhor”.

***

# 16\. Arquitetura recomendada para o seu sistema

Um sistema tecnicamente sólido poderia ter os seguintes módulos:

## Módulo 1 — Gerador de combinações

Responsável por gerar:

* apostas simples;
* combinações integrais;
* grupos-base;
* subconjuntos candidatos.

## Módulo 2 — Motor de cobertura

Para cada aposta, calcular quais cenários ela cobre:

```math
\text{cobre}(D,B,t)= \begin{cases} 1,|D\cap B|\geq t \\ 0, \text{caso contrário} \end{cases}
```

## Módulo 3 — Otimizador

Oferecer diferentes estratégias:

* gulosa;
* programação inteira;
* busca local;
* simulated annealing;
* algoritmo genético;
* programação por restrições.

## Módulo 4 — Verificador exato

Deve testar todas as combinações relevantes e informar:

* menor pontuação garantida;
* maior pontuação;
* quantidade de cenários cobertos;
* quantidade de cenários não cobertos;
* garantia condicional utilizada.

## Módulo 5 — Simulador

Pode simular milhares ou milhões de sorteios para estimar:

* frequência de premiações;
* distribuição de acertos;
* retorno esperado;
* variância;
* risco de perda.

A simulação não substitui a garantia matemática, mas ajuda a comparar métodos.

## Módulo 6 — Relatório de transparência

Cada fechamento deve informar:

* quantidade de dezenas-base;
* quantidade de apostas;
* custo total;
* cobertura exata;
* garantias condicionais;
* quantidade de combinações duplicadas;
* critérios estatísticos usados;
* limitações.

***

# 17\. Comparação dos principais métodos

| Método | Garantia matemática | Custo computacional | Melhor uso |
| ------ | ------------------: | ------------------: | ---------- |
| Fechamento integral | Sim, sob condição definida | Baixo para grupos pequenos | Garantia máxima |
| Fechamento reduzido | Às vezes | Médio | Economizar apostas |
| Covering design | Sim, se construído e verificado | Alto | Cobertura formal |
| Wheel system | Depende do projeto | Baixo a alto | Geração prática de apostas |
| Programação inteira | Pode fornecer ótimo ou limite | Alto | Otimização exata |
| Busca gulosa | Não necessariamente | Baixo | Solução rápida inicial |
| Busca local | Não necessariamente | Baixo/médio | Melhorar soluções existentes |
| Simulated annealing | Não necessariamente | Médio | Escapar de ótimos locais |
| Algoritmo genético | Não necessariamente | Médio/alto | Explorar muitas soluções |
| Frequência histórica | Não | Baixo | Preferência ou filtro |
| Machine learning | Não | Médio/alto | Pesquisa experimental, não previsão garantida |

***

# Conclusão

Os métodos mais importantes para a Lotofácil são:

1. **Fechamento integral**, quando se deseja garantia combinatória e o orçamento permite;
2. **Fechamento reduzido**, quando se aceita uma garantia menor em troca de menos apostas;
3. **Covering designs**, para formalizar a cobertura de subconjuntos;
4. **Set covering e programação inteira**, para buscar o menor conjunto de apostas que satisfaça uma garantia;
5. **Busca gulosa e busca local**, para soluções rápidas;
6. **Simulated annealing e algoritmos genéticos**, para problemas grandes e difíceis;
7. **Programação por restrições**, quando há muitas regras simultâneas;
8. **Estatísticas históricas**, apenas como critérios de organização, diversificação ou preferência — não como previsão comprovada.

A distinção mais importante para o seu sistema é separar claramente três conceitos:

* **aumento de probabilidade por comprar mais combinações**;
* **garantia matemática condicionada a um grupo-base**;
* **heurística de seleção sem garantia estatística**.

A literatura sobre fechamentos confirma que o problema pode ser tratado como cobertura combinatória e otimização discreta\, mas também mostra que encontrar sistemas mínimos pode ser computacionalmente difícil\. \[1\] \[2\] \[4\]

### Fontes

* [CAIXA — Lotofácil: regras, apostas e premiação](https://loterias.caixa.gov.br/Paginas/Lotofacil.aspx)
* [Optimal covering designs: complexity results and new bounds](https://www.sciencedirect.com/science/article/pii/S0166218X04001180)
* [Applying constraint programming to minimal lottery designs](https://link.springer.com/article/10.1007/s10601-024-09368-5)
* [Heuristic algorithm for solving the integer programming of the lottery problem](https://www.sciencedirect.com/science/article/pii/S1026309812000909)
* [Integer Programming — Wolfram MathWorld](https://mathworld.wolfram.com/IntegerProgramming.html)
* [Simulated Annealing — MIT](https://www.mit.edu/~dbertsim/papers/Optimization/Simulated%20annealing.pdf)
* [Combining Genetic Algorithms and Simulated Annealing for Lotto Designs](https://combinatorialpress.com/article/jcmcc/Volume%20045/vol-045-paper%208.pdf)
* [Lottery wheels e covering designs — MathOverflow](https://mathoverflow.net/questions/67334/a-generalization-of-covering-designs-and-lottery-wheels)
* [The “Gambler’s Fallacy” in Lottery Play — NBER](https://www.nber.org/papers/w3769)
