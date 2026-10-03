# Real review seeding kit (2026-10-02)

Goal: the first 200 genuine reviews on RuinMyTrip, written by people Nima actually knows, about
places they actually went. Every review is real, first person, under the writer's own account.

## Rules (non negotiable)

* Only places the writer personally visited. No reviews of places they have not been.
* The writer makes their own account. Nobody writes a review for somebody else, and nobody posts
  under Nima's account but Nima.
* No payment or gift for a review, and never for a positive one. Honest, including the bad parts:
  the bad parts are what the site is for.
* Nima and his team do not "fill in" missing reviews. A place with no review stays empty.

## Why it is easy now (live 2026-10-02)

* No account needed to start: the review form opens for anybody.
* On a place page, one tap on a star under "Been to X?" opens the form with the rating chosen.
* Headline is optional; a review is a star rating plus two or three sentences (40 characters minimum).
* Account step is email, password and a 16+ tick, with the review shown back so nothing is lost.
  The review goes live when they click the confirmation email.

## The links (attribution built in)

Start here, search for any place: 
`https://ruinmytrip.com/contribute?utm_source=friends&utm_campaign=seed-reviews`

By city (pick the city, then tap "Write a review" next to the place):
`https://ruinmytrip.com/d/<city>/places?utm_source=friends&utm_campaign=seed-reviews`

To see who each wave came from, add `&utm_content=<wave>` (for example `wave1-family`,
`wave1-friends`, `wave2-work`). Never a person's name in a link.

Cities on the site (85): Accra, Amsterdam, Athens, Banff, Bangkok, Barcelona, Berlin, Boracay,
Budapest, Buenos Aires, Buzios, Cairo, Cancun, Cape Town, Cartagena, Casablanca, Chiang Mai,
Copenhagen, Cusco, Dubai, Dublin, Dubrovnik, Edinburgh, Havana, Ho Chi Minh City, Hoi An, Hong Kong,
Istanbul, Jaipur, Kathmandu, Krakow, Kyoto, Las Vegas, Lima, Lisbon, London, Maldives, Manaus,
Manila, Marrakech, Medellin, Mexico City, Miami, Milan, Montego Bay, Munich, Nairobi, Naples, Nassau,
New Orleans, New York City, Nice, Oaxaca, Osaka, Paris, Petra, Phuket, Porto, Prague, Punta Cana,
Queenstown, Reykjavik, Rio de Janeiro, Rome, San Francisco, San Jose (Costa Rica), Santorini,
Seminyak, Seoul, Shanghai, Siem Reap, Singapore, Stockholm, Sydney, Tehran, Tel Aviv, Tokyo, Tulum,
Ubud, Vancouver, Venice, Vienna, Warsaw, Zanzibar, Zurich.

Known limit: a city that is not in this list cannot be reviewed yet. Ask people to start with
trips to these cities. If several people name the same missing city, that city gets added.

## Messages Nima can send (copy, edit, send from his own phone or email)

Text / WhatsApp:

> Hey! I built a travel site called RuinMyTrip where people write honest reviews of places they've
> been, the good and the bad, and find people going to the same places. Would you write a quick
> review of somewhere you went? Two minutes, no account needed to start:
> https://ruinmytrip.com/contribute?utm_source=friends&utm_campaign=seed-reviews&utm_content=wave1-friends
> Restaurants, hotels, tours, anything. Be honest, especially about what went wrong. Thank you!

Email:

> Subject: Could you review one place from a trip?
>
> I've been building RuinMyTrip, a travel site where real travelers say what a place was actually
> like, including what nearly ruined the trip. It only works if the first reviews are real, so I'm
> asking people I know rather than making anything up.
>
> If you've been to any of the cities on the site, pick a restaurant, hotel or tour you went to and
> write two or three honest sentences:
> https://ruinmytrip.com/contribute?utm_source=friends&utm_campaign=seed-reviews&utm_content=wave1-email
>
> You don't need an account to start; it asks for an email at the end so the review is yours.
> Thanks, it genuinely helps.

Follow up, three days later, only to people who said yes but did not write one:

> No rush at all, but if you still have a minute for that review, here's the link again: <link>

## Nima's own reviews

Nima writes 10 reviews from his own trips on his own account (`nima`). Send Claude the cities and
places he went; Claude replies with one direct link per place (form already bound to the place).

## How it is measured

`C:\Users\BL\rmt_kpi\KPI.md` (daily 07:10, task "RMT KPI Daily"): review starts, held reviews,
completed reviews, new accounts. By source and wave: /admin/funnel (utm_source=friends,
utm_content=wave). Target: 200 genuine reviews and 40 members from this kit in 30 days.
