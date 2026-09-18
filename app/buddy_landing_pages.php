<?php
/**
 * The travel buddy landing pages: one per country or cruise line people actually search for a
 * companion in. Twenty five, chosen by search intent, not generated. Every page carries its own
 * writing about how people pair up there, because a page that only swaps the country name into a
 * template is a doorway page and deserves to rank nowhere.
 *
 * Copy rules: nothing that expires. No prices, no fleet counts, no "newest ship", no year specific
 * dates. Fixed annual dates only (Bastille Day is always 14 July). No dashes.
 *
 * 'search' is a list of buddy search filters (see rmt_buddy_filters); results are merged.
 * 'dests' pins the city list for a page whose place is not a whole country (Bali).
 */

const RMT_BUDDY_LANDING = [

    /* ------------------------------------------------------------------ countries */

    'greece' => [
        'kind' => 'country', 'name' => 'Greece', 'country' => 'Greece',
        'search' => [['where' => 'Greece']],
        'title' => 'Travel buddies in Greece: find people going on your dates',
        'desc' => 'Going to Greece? See who else is going to Athens, Santorini and the islands on your dates, split ferries and cars, and meet up in public. Free, 18+.',
        'lede' => 'Greece is an island hopping country, and island hopping is better with somebody to split the ferry tickets, the rental car and the table at dinner with.',
        'sections' => [
            ['How people pair up in Greece', 'Most trips start in Athens and head out by ferry from Piraeus or Rafina. The simplest buddy match is two people on the same boat on the same day: you meet at the port, you already know you both like the same islands, and nobody has to commit to more than a crossing. On bigger islands like Crete and Naxos, sharing a rental car is the other classic reason to find somebody, because the best beaches are not on a bus route.'],
            ['When people go', 'May, June, September and early October are the months most travelers aim for: warm sea, open tavernas, fewer people on the Oia sunset wall. July and August are the busiest and hottest, and the meltemi wind can cancel smaller ferries in the Aegean, which is when having a buddy on the same island turns a lost day into a good one.'],
            ['Where travelers meet', 'The Acropolis is best early, before the heat, and it is an easy first meetup. On the islands people meet at the port, at the beach bar everybody already walks to, or at sunset, which on Santorini is a crowd anyway. Keep the first meeting somewhere public and busy.'],
        ],
        'tips' => [
            'Post your ferry day, not just your dates. Two people on the same crossing is the easiest match there is.',
            'Say which islands. "Greece in June" is a lot of places; "Naxos then Paros" finds the right person.',
            'Splitting a car on Crete or Naxos is a common and reasonable ask. Say so in your post.',
        ],
        'faq' => [
            ['How do I find a travel buddy for Greece?', 'Post your trip with your dates and the islands you are considering. Anyone whose Greece trip overlaps yours sees it, and you see theirs. Nobody can message you until you accept their request.'],
            ['Is Greece good for solo travelers?', 'Yes. The ferry network, hostels on the popular islands and a big summer crowd of other travelers make it one of the easiest places in Europe to travel alone and still find company.'],
            ['What is the best time to go to Greece with other travelers?', 'Late May to June and September to early October are when the most independent travelers are around and the islands are still open, without the peak summer crowds and heat.'],
        ],
    ],

    'thailand' => [
        'kind' => 'country', 'name' => 'Thailand', 'country' => 'Thailand',
        'search' => [['where' => 'Thailand']],
        'title' => 'Travel buddies in Thailand: meet people going on your dates',
        'desc' => 'Find travelers going to Bangkok, Chiang Mai, Phuket and the islands when you are. Share boats and day trips, meet in public, and only talk to people you accept. Free, 18+.',
        'lede' => 'Thailand is where a lot of people take their first big solo trip, and where a lot of them discover that the best days were the ones spent with somebody they met on the way.',
        'sections' => [
            ['How people pair up in Thailand', 'The well worn route runs Bangkok, north to Chiang Mai and Pai, then south to the islands. Because so many people follow some version of it, the chance that somebody is a few days ahead or behind you is high. Travel buddies here usually share a leg rather than a whole trip: a longtail boat charter to a quieter beach, a scooter day in the mountains, a cooking class, a night train.'],
            ['When people go', 'November to February is the cool, dry season for most of the country and the busiest time for travelers. Songkran, the Thai New Year water festival, runs 13 to 15 April and is much more fun in a group. In Chiang Mai, the lantern festivals in November draw people from all over the world. The Andaman coast around Phuket gets its heavy rain from roughly May to October.'],
            ['Where travelers meet', 'Hostel common rooms, night markets, Muay Thai nights and island boat trips. For a first meetup with somebody from here, a night market or a busy cafe is ideal: public, easy to leave, and a natural reason to be there.'],
        ],
        'tips' => [
            'Post the leg you want company for: "Chiang Mai to Pai on the 12th" gets better matches than "Thailand, a month".',
            'Boat charters and private drivers get much cheaper split two or four ways.',
            'Songkran and the November lantern festivals fill up. If you are going for one, say so.',
        ],
        'faq' => [
            ['How do I find a travel buddy in Thailand?', 'Post your dates and the places on your route. Travelers whose trips overlap yours see you, and you can send a request. Messaging only opens once both sides say yes.'],
            ['Is it easy to meet other travelers in Thailand?', 'Very. The main route is busy with independent travelers all year, especially from November to February, and many of them are traveling alone.'],
            ['When is Songkran?', 'Songkran is celebrated from 13 to 15 April every year, with the biggest water fights in Bangkok and Chiang Mai.'],
        ],
    ],

    'japan' => [
        'kind' => 'country', 'name' => 'Japan', 'country' => 'Japan',
        'search' => [['where' => 'Japan']],
        'title' => 'Travel buddies in Japan: find people going to Tokyo, Kyoto and Osaka',
        'desc' => 'See who is going to Japan on your dates. Find company for izakaya nights, day trips from Kyoto and Osaka, and hikes, and meet in public. Free, 18+.',
        'lede' => 'Japan is one of the easiest countries in the world to travel alone, which is exactly why people look for company for the parts that are more fun shared.',
        'sections' => [
            ['How people pair up in Japan', 'Solo travel is completely normal in Japan: ramen counters, capsule hotels and trains are built for one. Travelers usually look for a buddy for the social side instead. An izakaya crawl in Tokyo or Osaka, where ordering lots of small plates works better for two or three. A day trip from Kyoto to Nara or from Osaka to Himeji. A longer walk like the Kumano Kodo, or a night out in Shinjuku or Dotonbori.'],
            ['When people go', 'Cherry blossom season, usually late March to early April, and the autumn leaves in November are the peaks, and the peaks are busy. Golden Week, at the end of April into early May, is when Japan itself travels, so trains and hotels fill. June is the rainy season. Travelers who want fewer crowds aim for mid May, October and early December.'],
            ['Where travelers meet', 'Hostel lounges in Tokyo, Kyoto and Osaka are very social. A shrine or a famous crossing makes an easy first meetup spot. Many izakaya are happy to seat small groups of travelers, and it is a good way to try far more of the menu.'],
        ],
        'tips' => [
            'Post your city order and dates. Most visitors do Tokyo, Kyoto and Osaka in some order, so overlaps are common.',
            'Say what you want company for: food, hiking, nightlife or day trips.',
            'Cherry blossom and autumn leaf weeks are crowded. Mention it if your trip is timed for them.',
        ],
        'faq' => [
            ['Do I need a travel buddy in Japan?', 'No, Japan is very easy alone. People look for a buddy for izakaya nights, day trips and hikes, where company makes it more fun.'],
            ['When do most travelers visit Japan?', 'Late March to early April for cherry blossom and November for autumn leaves are the busiest times. Mid May and October are quieter and still pleasant.'],
            ['How does RuinMyTrip match travelers going to Japan?', 'You post where you are going and when. Anyone whose Japan dates overlap yours can see your trip and send a request, and messaging only opens once you accept.'],
        ],
    ],

    'italy' => [
        'kind' => 'country', 'name' => 'Italy', 'country' => 'Italy',
        'search' => [['where' => 'Italy']],
        'title' => 'Travel buddies in Italy: meet people going to Rome, Venice and the coast',
        'desc' => 'Find travelers going to Italy on your dates. Share a boat on the Amalfi Coast, an aperitivo in Milan or a day in Pompeii, and meet in public. Free, 18+.',
        'lede' => 'Italy is built around eating slowly with other people. A table for one is fine; a long dinner with somebody you met that morning is better.',
        'sections' => [
            ['How people pair up in Italy', 'Most first trips link Rome, Florence and Venice by train, and many add Naples, Pompeii and the Amalfi Coast. Travel buddies here tend to share the things that are priced for groups: a private boat around Capri, a driver along the Amalfi Coast, a cooking class, a wine tour in Tuscany. Walking the trails between the Cinque Terre villages is another popular day to share.'],
            ['When people go', 'April to June and September to October are the sweet spots: warm, lively, and not yet at peak prices and crowds. July and August are hot and busy on the coasts, and around Ferragosto on 15 August many Italians are on holiday themselves, so some city businesses close.'],
            ['Where travelers meet', 'Aperitivo hour, roughly early evening, is made for meeting people: a drink, some food, a busy piazza. Piazza Navona in Rome or the steps of a church make easy public meeting points. Food markets are good daytime options.'],
        ],
        'tips' => [
            'Post your train route and dates. Rome, Florence and Venice overlaps are very common.',
            'Boats and drivers on the Amalfi Coast are much cheaper split. Say if you want to share one.',
            'Summer on the coast books up early. If you are going in July or August, post early.',
        ],
        'faq' => [
            ['How do I find a travel buddy for Italy?', 'Post your trip with your cities and dates. Travelers going to the same places at the same time see it, and you can connect once both of you say yes.'],
            ['What is the best time to travel Italy with others?', 'April to June and September to October: lots of travelers around, good weather, and fewer crowds than midsummer.'],
            ['Can I meet other travelers for day trips in Italy?', 'Yes. Pompeii, Capri, the Amalfi Coast, Tuscany wine days and the Cinque Terre trails are the most common day trips people look for company on.'],
        ],
    ],

    'bali' => [
        'kind' => 'country', 'name' => 'Bali', 'country' => 'Indonesia',
        'search' => [['where' => 'Bali'], ['dest' => 'ubud-indonesia'], ['dest' => 'seminyak-bali-indonesia']],
        'dests' => ['ubud-indonesia', 'seminyak-bali-indonesia'],
        'title' => 'Travel buddies in Bali: find people going to Ubud, Canggu and Seminyak',
        'desc' => 'Going to Bali? See who is there on your dates, share a driver for the day, a sunrise trek or a boat to Nusa Penida, and meet in public. Free, 18+.',
        'lede' => 'Bali is small enough that everyone ends up in the same few places, and big enough that a day with a shared driver is the best way to see it.',
        'sections' => [
            ['How people pair up in Bali', 'Hiring a driver for the whole day is how most people see Bali: rice terraces, temples and waterfalls in one loop. It is a natural thing to split with one or two other travelers. The sunrise trek up Mount Batur starts in the dark and is more fun with company, and the fast boat to Nusa Penida and the islands is another popular shared day. Canggu and Ubud also have large communities of remote workers who are happy to meet people.'],
            ['When people go', 'The dry season, roughly April to October, is the most popular time. The wet season brings heavy afternoon showers but fewer people. Nyepi, the Balinese Day of Silence, usually in March, shuts the whole island for a day, including the airport, so check the date if your trip is in March.'],
            ['Where travelers meet', 'Beach clubs and sunset spots in Seminyak and Canggu, yoga studios and cafes in Ubud, and coworking spaces. For a first meetup, a busy cafe or a beach at sunset is ideal.'],
        ],
        'tips' => [
            'Post which part of Bali you are staying in. Ubud, Canggu, Seminyak and Uluwatu are different trips.',
            'A shared driver day is the easiest thing to ask a buddy for.',
            'Traveling in March? Check when Nyepi falls, because the island stops for a day.',
        ],
        'faq' => [
            ['How do I find a travel buddy in Bali?', 'Post your dates and where you are staying. Travelers in Bali at the same time see your trip and can send a request, and messaging opens only if you accept.'],
            ['Is Bali good for solo travelers?', 'Yes. It has a big community of solo travelers and remote workers, especially in Canggu and Ubud, and group activities like sunrise treks are easy to join.'],
            ['What is Nyepi?', 'Nyepi is the Balinese Day of Silence, usually in March, when the whole island, including the airport, shuts down for a day. The date changes each year.'],
        ],
    ],

    'mexico' => [
        'kind' => 'country', 'name' => 'Mexico', 'country' => 'Mexico',
        'search' => [['where' => 'Mexico']],
        'title' => 'Travel buddies in Mexico: meet people going to Mexico City, Tulum and Oaxaca',
        'desc' => 'Find travelers going to Mexico on your dates: Mexico City food tours, cenote days near Tulum, Day of the Dead in Oaxaca. Meet in public, free, 18+.',
        'lede' => 'Mexico rewards company: the food is meant for sharing, the cenotes are better with somebody to jump with, and Day of the Dead is a celebration, not a spectator sport.',
        'sections' => [
            ['How people pair up in Mexico', 'In Mexico City, people team up for taco crawls, Lucha Libre nights and day trips to the Teotihuacan pyramids. On the Caribbean coast, around Cancún and Tulum, the classic shared day is a cenote loop by car, or a trip out to the Mayan ruins. In Oaxaca, travelers pair up for mezcal tastings, markets and villages in the valley.'],
            ['When people go', 'The dry season, roughly November to April, is the most popular time for most of the country. Day of the Dead, around 31 October to 2 November, is one of the most requested times on the site, and Oaxaca and Mexico City are the favorite places for it. On the Caribbean coast, seaweed (sargassum) can be heavy in the warmer months.'],
            ['Where travelers meet', 'Markets, food tours and the central squares. In Mexico City, Roma and Condesa have lots of cafes and parks that make easy, public first meetups.'],
        ],
        'tips' => [
            'Going for Day of the Dead? Say so. It is the busiest buddy week of the year for Mexico.',
            'A rental car for a cenote day is a great thing to split.',
            'Say which region: Mexico City, the Yucatán and Oaxaca are very different trips.',
        ],
        'faq' => [
            ['How do I find a travel buddy for Mexico?', 'Post your trip with your city and dates. Travelers going to the same place at the same time can find you, and you choose who to accept.'],
            ['When is Day of the Dead in Mexico?', 'The main days are 1 and 2 November, with celebrations often starting on 31 October. Oaxaca and Mexico City are especially popular.'],
            ['Is Mexico City good for meeting other travelers?', 'Yes. Food tours, walking tours and the cafes of Roma and Condesa make it easy to meet people.'],
        ],
    ],

    'portugal' => [
        'kind' => 'country', 'name' => 'Portugal', 'country' => 'Portugal',
        'search' => [['where' => 'Portugal']],
        'title' => 'Travel buddies in Portugal: meet people going to Lisbon and Porto',
        'desc' => 'See who is going to Portugal on your dates. Share a Douro Valley day, a surf lesson or a Sintra trip, meet in public, and stay in control of who can message you.',
        'lede' => 'Portugal is compact, easy and social. You can be in Lisbon, Sintra and on a surf beach in the same week, and it is nicer with somebody to share it with.',
        'sections' => [
            ['How people pair up in Portugal', 'Lisbon and Porto are the two bases. From Lisbon, people team up for Sintra, the coast at Cascais and surf towns like Ericeira and Peniche. From Porto, the Douro Valley wine day is the classic trip to share. Porto is also a starting point for the Portuguese route of the Camino de Santiago, and walkers often look for company on the first stages.'],
            ['When people go', 'Spring and early autumn are ideal. June is festival month: Lisbon celebrates Santo António on the night of 12 to 13 June and Porto celebrates São João on the night of 23 to 24 June, with street parties that are far better in a group.'],
            ['Where travelers meet', 'Miradouros (viewpoints) at sunset, the food halls, and walking tours. A viewpoint in Lisbon or the riverside in Porto are easy, public first meetups.'],
        ],
        'tips' => [
            'Walking the Camino from Porto? Post your start date.',
            'In June, say if you are going for Santo António or São João.',
            'A Douro Valley day is a good thing to share with one or two others.',
        ],
        'faq' => [
            ['How do I find a travel buddy for Portugal?', 'Post your dates and cities. Travelers going to Lisbon or Porto at the same time will see your trip and can send a request.'],
            ['When are the Lisbon and Porto festivals?', 'Lisbon celebrates Santo António on the night of 12 to 13 June and Porto celebrates São João on the night of 23 to 24 June.'],
            ['Can I find people to walk the Camino Portugués with?', 'Yes. Post your trip with Porto as the start and your dates, and mention the Camino so walkers on the same days can find you.'],
        ],
    ],

    'spain' => [
        'kind' => 'country', 'name' => 'Spain', 'country' => 'Spain',
        'search' => [['where' => 'Spain']],
        'title' => 'Travel buddies in Spain: find people going to Barcelona and beyond',
        'desc' => 'Find travelers going to Spain on your dates: tapas nights, festivals, the Camino de Santiago. Meet in public and only talk to people you accept. Free, 18+.',
        'lede' => 'Spain eats late, stays out late and celebrates constantly. It is one of the best countries in Europe to meet people, and one of the worst to eat dinner alone at ten at night.',
        'sections' => [
            ['How people pair up in Spain', 'Tapas are designed for sharing, so a tapas crawl is the most natural buddy plan in the country. Barcelona is the most visited city, and people team up for the beach, Montjuïc and nights in the Gothic Quarter. The Camino de Santiago is the other big one: thousands of walkers start alone each year and most of them walk at least part of it with people they met on the way.'],
            ['When people go', 'Spring and autumn are the most comfortable. Festivals set a lot of trips: San Fermín in Pamplona runs 6 to 14 July, La Tomatina in Buñol happens on the last Wednesday of August, and Barcelona celebrates La Mercè in late September.'],
            ['Where travelers meet', 'Tapas bars, markets like La Boqueria, beach promenades and walking tours. Dinner is late, often after nine, so meeting for a drink and tapas before is common.'],
        ],
        'tips' => [
            'Going to a festival? Name it in your post. Festival weeks are when people most want company.',
            'Walking the Camino? Post your start town and date.',
            'Dinner starts late in Spain. Plan meetups for the evening, not the afternoon.',
        ],
        'faq' => [
            ['How do I find a travel buddy in Spain?', 'Post your trip with your city and dates. Travelers going to the same place at the same time can see it and send a request.'],
            ['When is San Fermín?', 'San Fermín in Pamplona runs from 6 to 14 July every year.'],
            ['Can I find people to walk the Camino de Santiago with?', 'Yes. Post your start point and date, and mention the Camino, so walkers starting around the same time can find you.'],
        ],
    ],

    'france' => [
        'kind' => 'country', 'name' => 'France', 'country' => 'France',
        'search' => [['where' => 'France']],
        'title' => 'Travel buddies in France: meet people going to Paris and the Riviera',
        'desc' => 'See who is going to Paris, Nice and the rest of France on your dates. Find company for picnics, museums, the Riviera and festivals. Meet in public, free, 18+.',
        'lede' => 'Paris can feel surprisingly lonely for a solo traveler. A picnic by the Seine with somebody who is also there for a week fixes that fast.',
        'sections' => [
            ['How people pair up in France', 'In Paris, travelers team up for museums, food markets, picnics by the Seine or Canal Saint Martin and evenings out. On the Riviera, the coast train from Nice to Monaco and Menton makes easy shared day trips, and people split boat days and beach clubs. Many visitors add a day trip to Versailles or the Loire castles.'],
            ['When people go', 'Spring and early autumn are the most pleasant. Fête de la Musique on 21 June fills the streets with free concerts, and Bastille Day on 14 July ends with fireworks at the Eiffel Tower. In August many Parisians leave on holiday, so some neighborhood places close while the Riviera is at its busiest.'],
            ['Where travelers meet', 'Parks, markets, museum cafes and the banks of the Seine. A public square or a busy cafe terrace makes an easy first meetup.'],
        ],
        'tips' => [
            'Paris for a week? Post the dates and what you want company for: museums, food or nights out.',
            'On the Riviera, the coast train makes day trips easy to share.',
            'Fête de la Musique and Bastille Day are great nights to have a group.',
        ],
        'faq' => [
            ['How do I find a travel buddy for Paris or France?', 'Post your dates and city. Travelers in the same place at the same time will see your trip and can send a request.'],
            ['When is Fête de la Musique?', 'Fête de la Musique is on 21 June every year, with free concerts all over France.'],
            ['Is Paris good for solo travelers?', 'Yes, and it is easy to find company for museums, walks and picnics if you post your dates.'],
        ],
    ],

    'vietnam' => [
        'kind' => 'country', 'name' => 'Vietnam', 'country' => 'Vietnam',
        'search' => [['where' => 'Vietnam']],
        'title' => 'Travel buddies in Vietnam: find people on your route north or south',
        'desc' => 'Find travelers doing Vietnam on your dates: Ha Giang, Hoi An, Ho Chi Minh City and the coast. Share buses, loops and boats, and meet in public. Free, 18+.',
        'lede' => 'Almost everyone travels Vietnam in a straight line, north to south or south to north, which makes it one of the easiest countries to find people on the same route.',
        'sections' => [
            ['How people pair up in Vietnam', 'The route runs between Hanoi and Ho Chi Minh City, with stops like Ha Long Bay, Ninh Binh, Hue, Hoi An and Da Lat. People team up for sleeper buses and trains, Ha Long Bay boat trips, and the Ha Giang loop in the far north, which many travelers ride with a local driver as part of a group. In Hoi An, people share bikes out to the beach and tailor visits.'],
            ['When people go', 'The weather varies a lot from north to south, so there is usually a good region at any time. The central coast around Hoi An and Hue gets heavy rain in autumn and can flood around October and November. Tết, the Lunar New Year in late January or February, is a big celebration but many businesses close for several days.'],
            ['Where travelers meet', 'Hostels on the main route are very social, and so are street food spots and night markets. In Hoi An, the old town on a lantern evening is an easy public place to meet.'],
        ],
        'tips' => [
            'Post your direction and your rough dates in each stop. Same route travelers find you that way.',
            'Doing the Ha Giang loop? Say when you plan to start.',
            'Check if your dates overlap Tết, because many places close.',
        ],
        'faq' => [
            ['How do I find a travel buddy for Vietnam?', 'Post your trip with your route and dates. Travelers going the same way at the same time can see it and send a request.'],
            ['Is Vietnam easy for solo travelers?', 'Yes. The north to south route is busy with independent travelers, and hostels and tours make it easy to meet people.'],
            ['When is Tết in Vietnam?', 'Tết is the Lunar New Year, in late January or February depending on the year. Many shops and restaurants close for several days.'],
        ],
    ],

    'colombia' => [
        'kind' => 'country', 'name' => 'Colombia', 'country' => 'Colombia',
        'search' => [['where' => 'Colombia']],
        'title' => 'Travel buddies in Colombia: meet people going to Medellín and Cartagena',
        'desc' => 'See who is going to Colombia on your dates. Find company for Guatapé, the Lost City trek, Tayrona and salsa nights. Meet in public, free, 18+.',
        'lede' => 'Colombia is one of the most social countries in South America, and travelers here tend to move in loose groups from Medellín to the coast.',
        'sections' => [
            ['How people pair up in Colombia', 'Medellín is the base most people start from, with day trips to Guatapé and coffee farms. On the Caribbean coast, Cartagena, Santa Marta and Tayrona National Park are the next stops. The Lost City trek (Ciudad Perdida) takes several days through the jungle from near Santa Marta and is done in guided groups, so many people look for friends to book it with.'],
            ['When people go', 'December to March is dry on the Caribbean coast and a popular time to travel. Medellín holds its Feria de las Flores in August. Barranquilla Carnival, one of the biggest in the world, happens in February or March before Lent.'],
            ['Where travelers meet', 'Salsa classes and bars, walking tours, hostels in Medellín and the old town in Cartagena. For a first meeting, keep it public, in the daytime and in a busy area, the same as anywhere.'],
        ],
        'tips' => [
            'Planning the Lost City trek? Post your dates so others can book the same group.',
            'Say if you want company for salsa nights; it is a very common ask.',
            'Meet in busy public places, and keep your own plans for getting back.',
        ],
        'faq' => [
            ['How do I find a travel buddy for Colombia?', 'Post your trip with your cities and dates. Travelers in Colombia at the same time can see it and send a request, and messaging only opens if you accept.'],
            ['Can I find people to do the Lost City trek with?', 'Yes. Post your dates and mention the trek so other travelers can join the same group.'],
            ['When is the Feria de las Flores in Medellín?', 'The Feria de las Flores takes place in Medellín in August.'],
        ],
    ],

    'peru' => [
        'kind' => 'country', 'name' => 'Peru', 'country' => 'Peru',
        'search' => [['where' => 'Peru']],
        'title' => 'Travel buddies in Peru: find people going to Cusco and Machu Picchu',
        'desc' => 'Find travelers going to Peru on your dates: the Inca Trail, Salkantay, Rainbow Mountain and Lima food. Meet in public and connect only with people you accept.',
        'lede' => 'Peru is a country of treks, and treks are done in groups. Finding your group before you land makes the whole trip easier.',
        'sections' => [
            ['How people pair up in Peru', 'Cusco is the hub. From there, people head to Machu Picchu by the Inca Trail, the Salkantay trek or the train, and do day trips to the Sacred Valley and Rainbow Mountain. Inca Trail permits are limited and sell out months ahead, so people who want to go together need to plan early. Lima is the food capital, and a food tour or a ceviche lunch is a great first meetup.'],
            ['When people go', 'The dry season in the Andes, roughly May to September, is the most popular time for trekking. The Inca Trail closes every February for maintenance. Cusco sits at about 3,400 meters, so most people spend a couple of easy days there before hiking, which is a good time to meet the people they will trek with.'],
            ['Where travelers meet', 'The Plaza de Armas in Cusco, hostels, and the tour agencies around the square. In Lima, Miraflores and Barranco are easy, public places to meet.'],
        ],
        'tips' => [
            'Doing the Inca Trail? Post early. Permits are limited and sell out.',
            'Say which trek you want: Inca Trail, Salkantay or Lares.',
            'Plan a couple of rest days in Cusco for the altitude. They are good days to meet your group.',
        ],
        'faq' => [
            ['How do I find a travel buddy for Peru?', 'Post your trip with your dates and whether you plan to trek. Travelers in Peru at the same time can find you and send a request.'],
            ['When is the Inca Trail closed?', 'The classic Inca Trail closes every February for maintenance.'],
            ['Can I find people to trek Salkantay or the Inca Trail with?', 'Yes. Post your dates and the trek so people planning the same route can connect with you.'],
        ],
    ],

    'morocco' => [
        'kind' => 'country', 'name' => 'Morocco', 'country' => 'Morocco',
        'search' => [['where' => 'Morocco']],
        'title' => 'Travel buddies in Morocco: meet people going to Marrakech and the Sahara',
        'desc' => 'See who is going to Morocco on your dates. Share a Sahara trip, a riad or a day in the Atlas Mountains, meet in public, and only talk to people you accept.',
        'lede' => 'Morocco is intense in the best way, and a lot of travelers find the medinas and the desert easier, and more fun, with a companion.',
        'sections' => [
            ['How people pair up in Morocco', 'Marrakech is where most trips start. The classic shared trip is the drive to the Sahara at Merzouga, usually two or three days with a night in a desert camp, which tour groups and private drivers both run. People also team up for the Atlas Mountains, the blue city of Chefchaouen, Fes and the coast at Essaouira. Many solo travelers, especially women, like having a buddy for the first walk through a busy medina.'],
            ['When people go', 'Spring and autumn are the most comfortable; summer is very hot inland and winter nights in the desert are cold. Ramadan moves about eleven days earlier each year; travel during it is possible, but daytime rhythms change and some places keep shorter hours.'],
            ['Where travelers meet', 'Riad rooftops, cooking classes, hammams and the main squares. Jemaa el Fna in Marrakech is always busy and makes an easy public meeting point.'],
        ],
        'tips' => [
            'Going to the Sahara? Post your dates so you can share a driver or book the same tour.',
            'Say if you want company for the medina on your first day.',
            'Check whether your dates fall in Ramadan.',
        ],
        'faq' => [
            ['How do I find a travel buddy for Morocco?', 'Post your trip with your dates and route. Travelers going at the same time will see it and can send a request.'],
            ['Can I share a Sahara desert trip with other travelers?', 'Yes. Post your dates and mention the Sahara, so others going from Marrakech around the same time can join.'],
            ['Is Morocco good for solo travelers?', 'Many solo travelers go, and a lot of them find it easier with a companion for the medinas and long drives.'],
        ],
    ],

    'iceland' => [
        'kind' => 'country', 'name' => 'Iceland', 'country' => 'Iceland',
        'search' => [['where' => 'Iceland']],
        'title' => 'Travel buddies in Iceland: share the Ring Road and the northern lights',
        'desc' => 'Find travelers going to Iceland on your dates to split a car or campervan on the Ring Road, chase the northern lights and hike. Meet in public, free, 18+.',
        'lede' => 'Iceland is the country where a travel buddy saves you the most money: the car is the trip, and a car costs the same with one person or four.',
        'sections' => [
            ['How people pair up in Iceland', 'The Ring Road, Route 1, loops about 1,300 kilometers around the island and most people drive it over a week or more, or pick a region like the south coast or the Snæfellsnes peninsula. Splitting a rental car or campervan is the most common reason travelers look for a buddy here. Glacier walks, ice caves and whale watching are also shared bookings.'],
            ['When people go', 'Summer brings very long days, with the midnight sun around June, and it is the only time the highland F roads are open (they need a four wheel drive). The northern lights season runs roughly September to April, when the nights are dark enough. Winter driving takes experience, so many people prefer to share it.'],
            ['Where travelers meet', 'Reykjavík is small, and cafes, hot pools and the harbor are easy public meeting points. Many people meet their road trip companion over a coffee in Reykjavík before committing to a week in a car.'],
        ],
        'tips' => [
            'Post your dates and whether you want to split a car or a campervan.',
            'Say how long you want to drive: the full Ring Road or one region.',
            'Meet in Reykjavík before you commit to a week on the road together.',
        ],
        'faq' => [
            ['How do I find someone to share an Iceland road trip with?', 'Post your dates and say you want to split a car. Travelers in Iceland at the same time can find you, and you can meet in Reykjavík first.'],
            ['When can you see the northern lights in Iceland?', 'Roughly September to April, when the nights are dark, on clear nights with good aurora activity.'],
            ['How long is the Ring Road?', 'Route 1, the Ring Road, is about 1,300 kilometers around the island.'],
        ],
    ],

    'croatia' => [
        'kind' => 'country', 'name' => 'Croatia', 'country' => 'Croatia',
        'search' => [['where' => 'Croatia']],
        'title' => 'Travel buddies in Croatia: meet people for Dubrovnik, Split and a week at sea',
        'desc' => 'See who is going to Croatia on your dates. Share a sailing week, island ferries or a Plitvice day, meet in public, and only talk to people you accept.',
        'lede' => 'Croatia is best seen from the water, and sailing weeks where travelers book by the cabin are one of the easiest ways to meet a whole boat of people.',
        'sections' => [
            ['How people pair up in Croatia', 'The coast runs from Split to Dubrovnik with islands like Hvar, Brač, Vis and Korčula in between. Island hopping by ferry is easy and cheap to share, and week long sailing trips where people book by the cabin are very popular with solo travelers and pairs of friends. Plitvice Lakes, inland, is the big day trip, and Game of Thrones fans walk Dubrovnik together.'],
            ['When people go', 'June and September are the favorite months: warm sea, open islands, fewer people than July and August, which are the peak. Split and Hvar have a big summer party scene, while the shoulder months are calmer.'],
            ['Where travelers meet', 'The waterfront promenades in Split and Dubrovnik, beach bars and ferry ports. The old town walls in Dubrovnik are an easy public meeting spot early in the day.'],
        ],
        'tips' => [
            'Looking to sail? Post your dates and say so. Cabin shares fill up for summer.',
            'Post your island order. Split, Hvar and Dubrovnik overlaps are common.',
            'June and September are the best months to find company without peak crowds.',
        ],
        'faq' => [
            ['How do I find a travel buddy for Croatia?', 'Post your trip with your dates and islands. Travelers in Croatia at the same time can see it and send a request.'],
            ['Can I find people to share a sailing week in Croatia?', 'Yes. Post your dates and mention sailing so travelers looking to fill a cabin or a boat can find you.'],
            ['When is the best time to visit Croatia?', 'June and September offer warm sea and open islands without the peak summer crowds.'],
        ],
    ],

    /* ------------------------------------------------------------------ cruise lines */

    'royal-caribbean' => [
        'kind' => 'cruise', 'name' => 'Royal Caribbean', 'line' => 'Royal Caribbean',
        'search' => [['type' => 'cruise', 'line' => 'Royal Caribbean']],
        'title' => 'Royal Caribbean cruise buddies: find people on your sailing',
        'desc' => 'Find other travelers on your Royal Caribbean ship and sailing date. Plan shore days, meet on board and connect only with people you accept. Free, 18+.',
        'lede' => 'Royal Caribbean ships are floating resorts with thousands of guests. Knowing a few of them before you board turns a big ship into a small one.',
        'sections' => [
            ['How people pair up on Royal Caribbean', 'The line is known for its large ships and onboard attractions, and it sails a lot of short Bahamas and Caribbean cruises from Florida and Texas, with stops at its private island, Perfect Day at CocoCay. Cruise buddies usually connect before the sailing to plan shore days together, meet for sail away, and have somebody to try the shows, the bars and the trivia nights with.'],
            ['Popular sailings', 'Caribbean and Bahamas cruises from Miami, Port Canaveral, Fort Lauderdale and Galveston are the most common, with Alaska sailings from Seattle in summer and Mediterranean and northern Europe cruises from European ports.'],
            ['Shore days together', 'Private excursions and beach days are often cheaper for a small group than per person on the ship. Finding people on your sailing lets you book a boat, a van or a snorkel trip together.'],
        ],
        'tips' => [
            'Post your ship and your exact sail date. Same ship, same date is the match.',
            'Say what you want company for: shore days, nights on board, or both.',
            'Traveling solo? Say so. Plenty of cruisers are, too.',
        ],
        'faq' => [
            ['How do I find people on my Royal Caribbean cruise?', 'Post your ship and sailing date. Anyone who posts the same sailing sees you, and you can connect once both of you say yes.'],
            ['Can I plan shore excursions with other cruisers?', 'Yes. That is the most common reason people connect before a cruise: to book a private tour or a beach day together.'],
            ['Is it safe to meet people from a cruise site?', 'Nobody can message you until you accept, and your cabin number and contact details are never shown. Meet in public areas of the ship or in port.'],
        ],
    ],

    'carnival' => [
        'kind' => 'cruise', 'name' => 'Carnival', 'line' => 'Carnival',
        'search' => [['type' => 'cruise', 'line' => 'Carnival']],
        'title' => 'Carnival cruise buddies: meet people on your Carnival sailing',
        'desc' => 'Find other travelers on your Carnival ship and date. Share shore days, meet at sail away and connect only with people you accept. Free, 18+.',
        'lede' => 'Carnival is about fun, and fun is easier with a group. A lot of Carnival cruisers are on short trips and want to make the most of every day.',
        'sections' => [
            ['How people pair up on Carnival', 'Carnival runs many short cruises of three to five nights to the Bahamas, Mexico and the Caribbean, from lots of US ports, so it is a popular choice for friend groups, first time cruisers and people celebrating something. Cruise buddies connect to meet at the pool, share tables, do karaoke and comedy nights, and team up on shore.'],
            ['Popular sailings', 'Short Bahamas and Mexico cruises from Galveston, Miami, Port Canaveral, Long Beach and New Orleans are among the most common, plus longer Caribbean itineraries and seasonal Alaska cruises.'],
            ['Shore days together', 'Beach breaks, snorkel trips and bar crawls in Cozumel, Nassau and other ports are cheaper and more fun as a group.'],
        ],
        'tips' => [
            'Post your ship and sail date so others on the same cruise can find you.',
            'Celebrating something? Say so; birthdays and bachelorette groups often team up.',
            'Say whether you want company on board, on shore, or both.',
        ],
        'faq' => [
            ['How do I meet people on my Carnival cruise before I go?', 'Post your ship and sail date. People on the same sailing can find you and send a request, and messaging opens only if you accept.'],
            ['Can solo travelers find company on Carnival?', 'Yes. Post that you are traveling solo and what you want to do, and other cruisers on the same sailing can connect.'],
            ['Can I share shore excursions with other Carnival guests?', 'Yes. Many people connect before sailing specifically to book a beach day or tour together.'],
        ],
    ],

    'norwegian' => [
        'kind' => 'cruise', 'name' => 'Norwegian Cruise Line', 'line' => 'Norwegian',
        'search' => [['type' => 'cruise', 'line' => 'Norwegian'], ['type' => 'cruise', 'line' => 'NCL']],
        'title' => 'Norwegian cruise buddies: find people on your NCL sailing',
        'desc' => 'Find other travelers on your Norwegian Cruise Line ship and sailing. Solo cruisers, shore days and nights on board. Meet in public, free, 18+.',
        'lede' => 'Norwegian is one of the most popular lines with solo cruisers, which makes it one of the easiest places to find company on board.',
        'sections' => [
            ['How people pair up on Norwegian', 'NCL is known for Freestyle Cruising, with no fixed dining times and a relaxed dress code, so plans are flexible and easy to share. Several ships have studio cabins designed for solo travelers, with a shared lounge for studio guests, which is where a lot of solo cruisers meet. Cruise buddies connect to share dinners, shows and shore days.'],
            ['Popular sailings', 'Caribbean and Bahamas cruises from Miami, Port Canaveral and New York, Alaska from Seattle, Hawaii inter island cruises, and Europe in summer.'],
            ['Shore days together', 'Splitting a private tour, a boat or a taxi in port makes a big difference to the price. Many people connect before the cruise just to plan port days.'],
        ],
        'tips' => [
            'In a studio cabin? Say so. Other solo cruisers on your sailing will look for you.',
            'Post your ship and exact sail date.',
            'Say what you want company for: dinners, shows, shore days or all three.',
        ],
        'faq' => [
            ['Is Norwegian good for solo cruisers?', 'Yes. Several NCL ships have studio cabins for solo travelers with a shared lounge, and the flexible dining makes it easy to join others.'],
            ['How do I find people on my NCL cruise?', 'Post your ship and sail date. Anyone on the same sailing can find you and send a request.'],
            ['What is Freestyle Cruising?', 'It is Norwegian\'s style of cruising with no fixed dining times or assigned tables and a relaxed dress code.'],
        ],
    ],

    'celebrity' => [
        'kind' => 'cruise', 'name' => 'Celebrity Cruises', 'line' => 'Celebrity',
        'search' => [['type' => 'cruise', 'line' => 'Celebrity']],
        'title' => 'Celebrity cruise buddies: meet people on your Celebrity sailing',
        'desc' => 'Find other travelers on your Celebrity Cruises ship and sailing date. Plan shore days in Europe and the Caribbean together. Meet in public, free, 18+.',
        'lede' => 'Celebrity attracts travelers who care about food, ports and a calmer ship, and finding a few like minded people on your sailing makes long port days much better.',
        'sections' => [
            ['How people pair up on Celebrity', 'Celebrity is a premium line with a strong focus on dining and destination heavy itineraries. Several of its newer ships have single staterooms for solo travelers. Cruise buddies tend to plan port days together, share a private guide in European ports, and meet for dinner or at the wine bar in the evening.'],
            ['Popular sailings', 'Caribbean cruises from Florida, Mediterranean and northern Europe cruises in summer, Alaska from Seattle and Vancouver, and small ship sailings in the Galápagos.'],
            ['Shore days together', 'European ports like Rome, Florence and Athens often mean long days with a lot to see. Splitting a private driver or guide with a few people from your ship is a popular way to do it.'],
        ],
        'tips' => [
            'Post your ship and sail date, and your port list if you know it.',
            'On a Europe sailing? Say if you want to share a private guide or driver.',
            'Traveling solo? Say so. Plenty of Celebrity guests are, too.',
        ],
        'faq' => [
            ['How do I find people on my Celebrity cruise?', 'Post your ship and sail date. Guests on the same sailing can find you and send a request.'],
            ['Can I share a private tour with other Celebrity guests?', 'Yes. Many travelers connect before a Europe sailing to share a driver or guide in port.'],
            ['Does Celebrity have cabins for solo travelers?', 'Several Celebrity ships have single staterooms. Check your ship when you book.'],
        ],
    ],

    'msc' => [
        'kind' => 'cruise', 'name' => 'MSC Cruises', 'line' => 'MSC',
        'search' => [['type' => 'cruise', 'line' => 'MSC']],
        'title' => 'MSC cruise buddies: find people on your MSC sailing',
        'desc' => 'Find other travelers on your MSC Cruises ship and date, in the Caribbean or the Mediterranean. Share shore days and meet on board. Free, 18+.',
        'lede' => 'MSC ships carry guests from all over the world, so finding a few people who speak your language and share your plans is a real help.',
        'sections' => [
            ['How people pair up on MSC', 'MSC is a Swiss based line with a very international mix of passengers and a big presence in the Mediterranean. Its Caribbean sailings stop at its private island, Ocean Cay MSC Marine Reserve. Cruise buddies connect to find people with the same language and interests, share tables and shows, and plan port days together.'],
            ['Popular sailings', 'Mediterranean cruises from ports like Barcelona, Genoa and Civitavecchia near Rome, northern Europe in summer, and Caribbean and Bahamas cruises from Miami, Port Canaveral, New York and Galveston.'],
            ['Shore days together', 'Mediterranean itineraries often have a new port every day. Sharing a taxi, a guide or a beach day with people from your ship saves money and time.'],
        ],
        'tips' => [
            'Post your ship, sail date and the language you speak.',
            'On a Mediterranean cruise? Post the ports so others can plan the same days.',
            'Say whether you want company on board, in port or both.',
        ],
        'faq' => [
            ['How do I find people on my MSC cruise?', 'Post your ship and sail date. Travelers on the same sailing can find you and send a request, and messaging opens only if you accept.'],
            ['Can I plan Mediterranean port days with other MSC guests?', 'Yes. Post your itinerary so others on the same sailing can share guides, taxis and beach days.'],
            ['What is Ocean Cay?', 'Ocean Cay MSC Marine Reserve is MSC\'s private island in the Bahamas, visited on many of its Caribbean cruises.'],
        ],
    ],

    'princess' => [
        'kind' => 'cruise', 'name' => 'Princess Cruises', 'line' => 'Princess',
        'search' => [['type' => 'cruise', 'line' => 'Princess']],
        'title' => 'Princess cruise buddies: meet people on your Princess sailing',
        'desc' => 'Find other travelers on your Princess Cruises ship and date, from Alaska to the Caribbean. Share shore days and meet on board. Free, 18+.',
        'lede' => 'Princess is one of the big names in Alaska cruising, and an Alaska cruise is full of excursions that are more fun, and often cheaper, with a small group.',
        'sections' => [
            ['How people pair up on Princess', 'Princess is especially known for Alaska, with cruises from Seattle and Vancouver and land and sea trips that add time inland. Its MedallionClass wearable makes getting around the ship easy. Cruise buddies connect to plan excursions like whale watching, glacier flights and train rides, and to meet for dinner and shows.'],
            ['Popular sailings', 'Alaska in summer, Caribbean from Fort Lauderdale, Mexico and California coast from Los Angeles, and Europe, Asia and Australia on longer itineraries.'],
            ['Shore days together', 'Alaska excursions such as whale watching, helicopter or floatplane trips and the White Pass railway are big ticket items. Booking with a group you met before sailing can make it easier to plan.'],
        ],
        'tips' => [
            'Post your ship, sail date and whether it is a land and sea trip.',
            'On an Alaska cruise? Say which excursions you are considering.',
            'Traveling solo? Say so, so other solo cruisers can find you.',
        ],
        'faq' => [
            ['How do I find people on my Princess cruise?', 'Post your ship and sail date. Guests on the same sailing can find you and send a request.'],
            ['Can I share Alaska excursions with other cruisers?', 'Yes. Post your sailing and the excursions you are interested in, so others on the same ship can join.'],
            ['Where do Princess Alaska cruises leave from?', 'Many Princess Alaska cruises leave from Seattle and Vancouver.'],
        ],
    ],

    'disney' => [
        'kind' => 'cruise', 'name' => 'Disney Cruise Line', 'line' => 'Disney',
        'search' => [['type' => 'cruise', 'line' => 'Disney']],
        'title' => 'Disney cruise buddies: find families and adults on your sailing',
        'desc' => 'Find other families and adults on your Disney Cruise Line ship and date. Plan port days and meet on board. Nobody can message you until you accept. 18+ members.',
        'lede' => 'On a Disney cruise the kids make friends in minutes. Finding the parents, or other adults, on your sailing before you board is the grown up version.',
        'sections' => [
            ['How people pair up on Disney', 'Disney Cruise Line sails mostly family cruises, with a strong kids program and adults only areas on board. Families connect before the sailing to meet at the pool, plan the private island day at Castaway Cay together, and share excursions. Adults without kids use it to find company for the adults only restaurants, lounges and pool.'],
            ['Popular sailings', 'Bahamas and Caribbean cruises from Port Canaveral and Florida, plus seasonal cruises from other US ports and Europe.'],
            ['Shore days together', 'Private beach days and excursions are often easier to organize with another family. Parents can also swap turns at the kids club and the adults only areas.'],
        ],
        'tips' => [
            'Post your ship and sail date, and whether you are traveling with kids.',
            'Families: say the rough ages of your kids, never their names.',
            'Adults only? Say so, so others without kids can find you.',
        ],
        'faq' => [
            ['How do I find other families on my Disney cruise?', 'An adult posts the ship and sail date. Families on the same sailing can find you, and messaging opens only if you accept.'],
            ['Can adults without kids find company on a Disney cruise?', 'Yes. Say you are traveling adults only so other adults on the same sailing can connect.'],
            ['Who can use RuinMyTrip?', 'Travel buddies is for members 18 and over. A parent posts the trip for the family.'],
        ],
    ],

    'holland-america' => [
        'kind' => 'cruise', 'name' => 'Holland America Line', 'line' => 'Holland America',
        'search' => [['type' => 'cruise', 'line' => 'Holland America']],
        'title' => 'Holland America cruise buddies: meet people on your sailing',
        'desc' => 'Find other travelers on your Holland America Line ship and date, in Alaska, Europe or on a long voyage. Share shore days and dinners. Free, 18+.',
        'lede' => 'Holland America guests tend to take longer cruises, and on a long cruise the people you share it with matter even more.',
        'sections' => [
            ['How people pair up on Holland America', 'Holland America is known for Alaska, longer itineraries and a calmer, more traditional ship with a strong music program. Many guests are experienced cruisers traveling as couples or solo. Cruise buddies connect to share dinner tables, trivia teams, card games and port days.'],
            ['Popular sailings', 'Alaska from Seattle and Vancouver, the Caribbean from Fort Lauderdale, Europe in summer, and long voyages and grand voyages around the world.'],
            ['Shore days together', 'On long itineraries with many ports, sharing guides and taxis with people you already know on board makes planning much easier.'],
        ],
        'tips' => [
            'Post your ship and sail date, and how long the cruise is.',
            'On a long voyage? Say so; others on the full voyage will look for company.',
            'Say if you want dinner companions, a trivia team or port day partners.',
        ],
        'faq' => [
            ['How do I find people on my Holland America cruise?', 'Post your ship and sail date. Guests on the same sailing can find you and send a request.'],
            ['Can solo travelers find company on Holland America?', 'Yes. Post that you are traveling solo, and others on your sailing can connect.'],
            ['Can I plan port days with other guests?', 'Yes. Post your itinerary and dates so others on the same ship can share guides and taxis.'],
        ],
    ],

    'virgin-voyages' => [
        'kind' => 'cruise', 'name' => 'Virgin Voyages', 'line' => 'Virgin',
        'search' => [['type' => 'cruise', 'line' => 'Virgin']],
        'title' => 'Virgin Voyages cruise buddies: find people on your adults only sailing',
        'desc' => 'Find other travelers on your Virgin Voyages ship and date. Adults only, social and made for meeting people. Share shore days and nights out. Free, 18+.',
        'lede' => 'Virgin Voyages is adults only and built to be social, which makes it one of the best ships to find people to hang out with.',
        'sections' => [
            ['How people pair up on Virgin Voyages', 'Every guest on Virgin Voyages is 18 or over, and the ships lean into nightlife, restaurants instead of a main dining room, and events. Cruise buddies connect to meet at the pool, share dinners, go to the shows and parties, and plan port days together. Many guests travel solo or as pairs of friends.'],
            ['Popular sailings', 'Caribbean cruises from Miami, including stops at The Beach Club at Bimini, Mediterranean cruises in summer, and other seasonal itineraries.'],
            ['Shore days together', 'Beach days, boat trips and nights out in port are a natural thing to share with people you already know on board.'],
        ],
        'tips' => [
            'Post your ship and sail date.',
            'Traveling solo? Say so. It is a common way to sail Virgin.',
            'Say whether you want company for nights out, shore days or both.',
        ],
        'faq' => [
            ['Is Virgin Voyages adults only?', 'Yes. All Virgin Voyages guests are 18 or over.'],
            ['How do I find people on my Virgin Voyages sailing?', 'Post your ship and sail date. Others on the same sailing can find you and send a request.'],
            ['Is Virgin Voyages good for solo travelers?', 'Many solo travelers sail with Virgin because the ships are social and adults only.'],
        ],
    ],

    'viking' => [
        'kind' => 'cruise', 'name' => 'Viking', 'line' => 'Viking',
        'search' => [['type' => 'cruise', 'line' => 'Viking']],
        'title' => 'Viking cruise buddies: meet people on your river or ocean voyage',
        'desc' => 'Find other travelers on your Viking river or ocean cruise. Adults only, destination focused and relaxed. Share port days and dinners. Free, 18+.',
        'lede' => 'Viking is adults only and about the places, not the ship, and the people you share a river or ocean voyage with become a big part of it.',
        'sections' => [
            ['How people pair up on Viking', 'Viking sails river cruises on rivers like the Danube, Rhine and Seine, and ocean cruises around the world. Guests are all 18 or over, there are no casinos, and the focus is on history and culture. Many guests are experienced travelers, often couples and solo travelers, who connect to share dinners, walking tours and free time in port.'],
            ['Popular sailings', 'European river cruises in spring, summer and the Christmas market season, and ocean cruises in Scandinavia, the Mediterranean and beyond.'],
            ['Shore days together', 'River cruises dock in the middle of towns, so the free afternoons after a guided walk are the perfect time to explore with people from your ship.'],
        ],
        'tips' => [
            'Post your ship, river or route, and sail date.',
            'On a Christmas market cruise? Say so; those sailings fill up.',
            'Traveling solo? Say so, so others on your sailing can find you.',
        ],
        'faq' => [
            ['Is Viking adults only?', 'Yes. Viking guests must be 18 or over.'],
            ['How do I find people on my Viking cruise?', 'Post your ship and sail date. Others on the same voyage can find you and send a request.'],
            ['Can I find company for free time in port?', 'Yes. Post your sailing and mention what you want to see, so others on the same voyage can join.'],
        ],
    ],
];
