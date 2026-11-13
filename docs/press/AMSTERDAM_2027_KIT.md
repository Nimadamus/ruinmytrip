# Amsterdam tourist tax vote: press kit (staged 2026-11-09, nothing sent)

Hook: Amsterdam's council is due to vote on the 2027 budget on 11 November 2026 (council papers list
the tax regulation under "Vaststellen Begroting 2027"; reported vote window 11 to 12 November). The
budget proposes raising the tourist tax from 12.5% to 16% of the room price in 2027, then one point
a year to 20% in 2031.

**Send nothing until (1) the vote result is confirmed from amsterdam.raadsinformatie.nl or the city,
(2) the owner approves the list and the text, (3) the owner confirms the sign off line.**

## The numbers (from /tourist-tax-calculator#same-trip, same rates as the calculator)

Two adults, one room, four nights at 200 euros a night before VAT, four star where a city prices by
category:

| City | Tax now | 2027 |
|---|---|---|
| Amsterdam | €100.00 | €128.00 if 16% passes |
| Milan | €80.00 | no change in our notes |
| Paris | €67.60 | no change in our notes |
| Barcelona | €67.20 | €75.20 from 1 April 2027 (city surcharge 5 to 6 euros) |
| Porto (lowest of 12) | €24.00 | no change in our notes |

Per night on a 200 euro room: 25 euros now, 32 euros at 16%, 40 euros at 20% (2031). VAT of 21% on
the room comes on top (national rate since 1 January 2026).

## Pitch draft (under 200 words, fill the result line on the day)

Subject: Amsterdam tourist tax: what the [passed/rejected] rise costs a couple

> Hi [first name],
>
> Amsterdam's council [approved/rejected] raising the tourist tax from 12.5% to 16% for 2027.
> We priced the same four night, 200 euro a night hotel stay for two in 12 euro cities using each
> city's official rate:
>
> * Amsterdam: 100 euros in tax today, 128 euros in 2027 at 16%.
> * Next highest: Milan 80, Paris 67.60, Barcelona 67.20 (75.20 after its April 2027 rise).
> * Lowest: Porto, 24 euros.
>
> So the same trip already costs over four times more in tax in Amsterdam than in Porto, before VAT.
> The full table, method and sources for every rate are here:
> https://ruinmytrip.com/tourist-tax-calculator#same-trip
>
> Happy to send the table as a spreadsheet or run other stay lengths or prices.
>
> [SIGN OFF: owner to confirm exact line]

## Targets (outlets that already covered the proposal or cover Amsterdam travel costs)

Contact names and addresses to be looked up only after owner approval of this list.

| Outlet | Why |
|---|---|
| Skift | Covered the proposal 5 June 2026 |
| Daily Hive (Canada) | Framed the rise for Canadian travellers |
| TravelMole | Ran Holidu's tourist tax ranking |
| NL Times | English language Dutch news, covers council votes |
| DutchNews.nl | Same |
| Euronews Travel | Regularly covers European tourist taxes |
| Travel Off Path | Covers entry fees and taxes for US travellers |
| Time Out Amsterdam | Local cost of visiting angle |

## After the vote (staged edits, owner OK before deploy)

1. `app/tourist_tax.php` RMT_TT_COMPARE_2027['amsterdam']['status']: "Decided: 16% from 1 January
   2027" (or remove the row's 2027 value if rejected).
2. /blog/amsterdam-tourist-tax-2026: replace "plans to raise" sentence with the decided rate and
   the council source; title unchanged.
3. Calculator tip for Amsterdam: add the 2027 rate once decided.
