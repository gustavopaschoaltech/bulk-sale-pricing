# Future Improvements

Este arquivo reúne ideias, correções e melhorias identificadas durante o desenvolvimento do Bulk Sale Pricing que não fazem parte da Issue atual.

## Regra de uso

- Itens deste arquivo não devem ser implementados automaticamente durante a Issue atual.
- Antes de implementar qualquer item, ele deve ser revisado e, quando fizer sentido, promovido para uma GitHub Issue própria.
- O objetivo é evitar perda de ideias sem aumentar indevidamente o escopo das Issues em andamento.

## Admin / UX

- [ ] Deixar o campo Sale Price bloqueado/read-only quando estiver sob controle de uma regra ativa
- [ ] Ajustar larguras das colunas na tabela de regras
- [ ] Na tabela de regras incluir um icone (lupa ou algo melhor) depois das colunas categorias e tags com um link para abrir uma nova janela de produtos padrao do woocomerce ja filtrando os produtos selecionados pela lógica dessa regra.
- [ ] Na tabela de regras substituir "move to trash", active, desactive por ícones, com explicação ao passar o mouse.
- [ ] Nas mensagens que aparecem após p.ex. ativação, desativação, conflitos, aqueles banners na parte superior da tela coloridos, sempre citar o nome da(s) regra(s) afetada na mensagem.
- [ ] Melhorar texto da tela "Confirm Sale Price Overwrite"
- [ ] Gerar um log em cada produto, registrando cada operação.
- [ ] Na tabela de regras colocar um icone de aviso amarelo triangulo com exclamação dentro ao lado do nome da regra, qd passar o mouse mostra um resumo do(s) conflito(s).

## Pricing

- [ ] 

## Product Matching

- [ ] Definir política para falha parcial durante ativação: se uma exceção ocorrer após alguns produtos já terem recebido o Sale Price, a regra pode permanecer inativa enquanto itens anteriores já foram alterados. Avaliar estratégia segura sem restaurar Sale Prices anteriores.
- [ ] 

## Rules / Conflicts

- [ ] Ao duplicar um produto no WooCommerce, impedir que o histórico `_bsp_product_history` seja copiado para o novo produto. A cópia deve iniciar com histórico BSP vazio.

## Testing

- [ ] 

## Performance

- [ ] 

## Documentation

- [ ] 
