// York City Configuration - Medieval Magic Walk
window.YorkConfig = {
    cityName: "York",
    citySlug: "york",
    mapCenter: [53.9600, -1.0807],
    mapZoom: 15,
    brandColor: '#8B4513',
    
    // Landmarks with historical information
    landmarks: [
        {
            id: 1,
            name: "York Art Gallery",
            lat: 53.96298032671358,
            lng: -1.0860692179472704,
            category: "Museum",
            description: "Built in 1879 for the Yorkshire Fine Art and Industrial Exhibition, York Art Gallery houses over 600 years of art history. The building's grand Italianate facade conceals treasures including the largest collection of William Etty paintings in the world and the Centre of Ceramic Art (CoCA) featuring 10,000 British Studio Ceramics. Following a major renovation in 2015, hidden Victorian timber trusses and ornate plasterwork were revealed after being concealed for over 60 years.",
            website: "https://www.yorkartgallery.org.uk/",
            openingHours: "Daily: 10am - 5pm",
            admissionFee: "Free (donations welcome)",
            hasGaussianSplat: false,
            splatFile: null,
            audioUrl: "assets/audio/york/york-art-gallery.mp3",
            audioDuration: "4:30",
            transcript: "assets/audio/york/transcript/SCRIPT.md",
            images: ["309f9b", "a64604", "ffdce1d"]
        },
        {
            id: 2,
            name: "Bootham Bar",
            lat: 53.96274720815963,
            lng: -1.085038387267359,
            category: "Historic",
            description: "Standing on the site of a Roman gateway for nearly 2,000 years, Bootham Bar is York's oldest surviving city gate. The imposing three-storey structure features stonework dating from the 11th century, with its main archway among the earliest medieval architecture in York. The gateway was damaged during the 1644 Siege of York but restored shortly after. Its portcullis and defensive features remain remarkably well-preserved, offering visitors a glimpse into medieval York's formidable defenses.",
            website: "https://www.historyofyork.org.uk/themes/norman/bootham-bar",
            openingHours: "24/7 (exterior viewing)",
            admissionFee: "Free",
            hasGaussianSplat: false,
            splatFile: null,
            audioUrl: null,
            audioDuration: null,
            images: ["50c983", "7561ca2", "3310b11"]
        },
        {
            id: 3,
            name: "York Minster",
            lat: 53.96257240486018,
            lng: -1.081010592620926,
            category: "Historic",
            description: "One of the world's most magnificent cathedrals, York Minster is the largest Gothic cathedral in Northern Europe. Construction began in 1220 and took over 250 years to complete. The Minster houses the largest expanse of medieval stained glass in Britain, including the famous Great East Window. Its twin western towers stand 60 meters tall, while the Central Tower reaches 72 meters. The cathedral sits atop Roman barracks, whose foundations can still be explored in the Undercroft.",
            website: "https://yorkminster.org/",
            openingHours: "Mon-Sat: 9am - 4:30pm, Sun: 12:45pm - 3:15pm",
            admissionFee: "Adults £13, Children £10",
            hasGaussianSplat: false,
            splatFile: null,
            audioUrl: null,
            audioDuration: null,
            images: ["cathedral-1", "cathedral-2", "081999"]
        },
        {
            id: 4,
            name: "The Shambles",
            lat: 53.95937380227282,
            lng: -1.0800757531304426,
            category: "Shopping",
            description: "The Shambles is one of Europe's best-preserved medieval shopping streets, dating back to the Domesday Book of 1086. The street's name derives from the Saxon word 'Shamel', meaning shelves for displaying meat - it was once home to 26 butcher shops. The overhanging timber-framed buildings lean so close together that neighbors could shake hands across the narrow lane. Today, the cobbled street houses independent shops, cafes, and boutiques in buildings that have stood for over 900 years.",
            website: "https://visitshambles.com/",
            openingHours: "Shops vary, street accessible 24/7",
            admissionFee: "Free",
            hasGaussianSplat: false,
            splatFile: null,
            audioUrl: null,
            audioDuration: null,
            images: ["49c068", "6d4941", "3e6700"]
        },
        {
            id: 5,
            name: "Clifford's Tower",
            lat: 53.955551209024804,
            lng: -1.0795290259718389,
            category: "Historic",
            description: "Standing proudly on its mound overlooking York, Clifford's Tower is the largest remaining part of York Castle. Built by William the Conqueror in 1068, the original wooden tower was destroyed and rebuilt in stone between 1245-1265. The tower witnessed one of medieval England's darkest episodes in 1190 when York's Jewish community sought refuge here during anti-Semitic riots. The site offers panoramic views across York and stands as a powerful reminder of the city's complex history.",
            website: "https://www.english-heritage.org.uk/visit/places/cliffords-tower-york/",
            openingHours: "Daily: 10am - 4pm (seasonal variations)",
            admissionFee: "Adults £7.50, Children £4.50",
            hasGaussianSplat: false,
            splatFile: null,
            audioUrl: null,
            audioDuration: null,
            images: ["a907a47", "72f39c", "1304b6f"]
        },
        {
            id: 6,
            name: "Museum Gardens",
            lat: 53.961625533084565,
            lng: -1.0877395719134586,
            category: "Park",
            description: "These stunning 10-acre botanical gardens are home to the ruins of St. Mary's Abbey, once the wealthiest Benedictine monastery in Northern England. Founded in 1088, the abbey's magnificent Gothic arches still stand amid landscaped grounds featuring over 40 species of trees. The gardens also house the Yorkshire Museum, medieval hospitium, and a section of Roman walls including the imposing Multangular Tower. This peaceful oasis in York's heart offers visitors a journey through 2,000 years of history.",
            website: "https://www.yorkmuseumstrust.org.uk/",
            openingHours: "Daily: 7:30am - 6pm (seasonal variations)",
            admissionFee: "Free",
            hasGaussianSplat: false,
            splatFile: null,
            audioUrl: null,
            audioDuration: null,
            images: ["50c983", "309f9b", "a64604"]
        }
    ],
    
    // Partner cafes
    partners: [
        {
            id: 101,
            name: "Bennett's Cafe and Bistro",
            lat: 53.962329963905546,
            lng: -1.0839396467823135,
            category: "Partner",
            description: "A charming family-run cafe serving artisan coffee, homemade cakes, and seasonal British cuisine. Popular with locals and visitors alike for its warm atmosphere and quality ingredients.",
            website: "https://www.bennettscafebistro.co.uk/",
            openingHours: "Mon-Sat: 9am - 5pm, Sun: 10am - 4pm",
            discount: "10% off with tour code",
            discountCode: "YORKCODE10",
            hasGaussianSplat: false,
            images: ["ffdce1d"]
        },
        {
            id: 102,
            name: "The Perky Peacock",
            lat: 53.95993445737397,
            lng: -1.0877399602774536,
            category: "Partner",
            description: "Independent coffee shop known for specialty coffee, fresh sandwiches, and delicious homemade treats. A cozy spot perfect for a refreshment break during your tour.",
            website: "https://www.theperkypeacock.co.uk/",
            openingHours: "Mon-Fri: 8am - 5pm, Sat-Sun: 9am - 5pm",
            discount: "Free cookie with any drink",
            discountCode: "YORKCODE10",
            hasGaussianSplat: false,
            images: ["3e6700"]
        }
    ],
    
    // Bus stop with updated coordinates
    busStop: {
        id: 999,
        name: "🚌 Tour Bus Pickup Point",
        lat: 53.96020087915435,
        lng: -1.0806842653401008,
        category: "BusStop",
        description: "Your Medieval Magic Walk tour bus will depart from here.",
        departureTime: "17:30",
        whatsappNumber: "+447912345678"
    },
    
    // Walking route with sequential waypoints
    routes: [
        {
            id: 1,
            name: "Medieval Magic Walk",
            color: "#8B4513",
            landmarks: [1, 2, 3, 4, 5, 6], // York Art Gallery → Bootham Bar → York Minster → The Shambles → Clifford's Tower → Museum Gardens
            waypoints: [
                // Start at bus stop
                [53.96020087915435, -1.0806842653401008],
                // Walk to York Art Gallery
                [53.96298032671358, -1.0860692179472704],
                [53.962472024006225, -1.085421762445791],
                // Bootham Bar
                [53.96274720815963, -1.085038387267359],
                [53.96257459283015, -1.084799489954492],
                [53.96269609710851, -1.084464213823738],
                [53.962341051143824, -1.083568356012796],
                [53.96245159445183, -1.0834930467961987],
                [53.96276033147166, -1.0824891131225298],
                [53.9629214107504, -1.0814395461032293],
                // York Minster
                [53.96257240486018, -1.081010592620926],
                [53.962320044922706, -1.0806455258373302],
                [53.962132116326096, -1.0800066589487936],
                [53.961952241018686, -1.0798971389107586],
                [53.96194418687294, -1.0796644088383063],
                [53.96075215750374, -1.0800249122963606],
                [53.96036017644326, -1.0804310491040732],
                [53.95988710501254, -1.0802618730149587],
                [53.959846041025955, -1.0803374842180435],
                // The Shambles
                [53.95937380227282, -1.0800757531304426],
                [53.958795473591785, -1.0796976971185304],
                [53.95836771054702, -1.0802560567720791],
                [53.95733079469345, -1.0785867940690337],
                [53.95711177284823, -1.0790404612857194],
                [53.956690837047944, -1.0790520937785018],
                [53.95640678853741, -1.078842708908421],
                [53.95596873402906, -1.0784006741826948],
                // Clifford's Tower
                [53.955551209024804, -1.0795290259718389],
                [53.95526715275038, -1.080314219234642],
                [53.95603718034201, -1.0809947200458618],
                [53.95664976990572, -1.081611242163322],
                [53.95752315234247, -1.0817445579034861],
                [53.95781075521934, -1.0823196194386349],
                [53.95907956695611, -1.0839657324730532],
                [53.95997194154104, -1.0846414294461602],
                [53.96095733774925, -1.0867907208533532],
                [53.96102500359963, -1.0872004519881073],
                // Museum Gardens
                [53.961625533084565, -1.0877395719134586],
                [53.961401392809826, -1.0875383004746608],
                [53.96161284593138, -1.0869560509552816],
                // Return to bus stop
                [53.96260243230536, -1.0857771754017065]
            ]
        }
    ],
    
    categoryColors: {
        'Historic': '#8B4513',
        'Museum': '#8B4513',
        'Park': '#228B22',
        'Shopping': '#D97706',
        'Landmark': '#8B4513',
        'Partner': '#D97706',
        'BusStop': '#DC2626'
    }
};
