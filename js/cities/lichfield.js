// Lichfield City Configuration
window.LichfieldConfig = {
    cityName: "Lichfield",
    citySlug: "lichfield",
    mapCenter: [52.6825, -1.8290],
    mapZoom: 15,
    brandColor: '#B92F7B',
    
    // Google Analytics 4 Measurement ID
    GA4_ID: 'G-XXXXXXXXXX',
    
    // Landmarks with UPDATED coordinates and audio
    landmarks: [
        {
            id: 1,
            name: "Lichfield Cathedral",
            lat: 52.68546265489443,
            lng: -1.83059269479981,
            category: "Historic",
            description: "The only medieval cathedral in Europe with three spires, known as the 'Ladies of the Vale'. Dating from 1195, it's one of the finest examples of Gothic architecture in England.",
            website: "https://www.lichfield-cathedral.org/",
            openingHours: "Mon-Sun: 7:30am - 6:15pm",
            admissionFee: "Free (donations welcome)",
            hasGaussianSplat: true,
            splatFile: "lichcath.splat",
            audioUrl: "assets/audio/cathedral/guide.mp3",
            audioDuration: "3:45",
            images: ["cath/cath1", "cath/cath2", "cath/cath3"]
        },
        {
            id: 2,
            name: "Samuel Johnson Birthplace",
            lat: 52.68346748442398,
            lng: -1.8279729684269301,
            category: "Museum",
            description: "Birthplace of Samuel Johnson, the famous 18th-century writer and lexicographer who created the first comprehensive English dictionary.",
            website: "https://www.samueljohnsonbirthplace.org.uk/",
            openingHours: "Tue-Sun: 10:30am - 4:30pm",
            admissionFee: "Adults £5.50, Children £3",
            hasGaussianSplat: false,
            audioUrl: "assets/audio/samuel-johnson/guide.mp3",
            audioDuration: "2:30",
            images: ["a907a47", "50c983"]
        },
        {
            id: 3,
            name: "Beacon Park",
            lat: 52.684828259344094,
            lng: -1.8363802384355299,
            category: "Park",
            description: "A beautiful Victorian park with 70 acres of landscaped gardens, a lake, and stunning views of the Cathedral.",
            website: "https://www.lichfielddc.gov.uk/beaconpark",
            openingHours: "Daily: Dawn to Dusk",
            admissionFee: "Free",
            hasGaussianSplat: false,
            audioUrl: "assets/audio/beacon-park/guide.mp3",
            audioDuration: "2:00",
            images: ["3e6700", "a64604"]
        },
        {
            id: 4,
            name: "Erasmus Darwin House",
            lat: 52.685067555869296,
            lng: -1.832694199575537,
            category: "Museum",
            description: "Former home of Erasmus Darwin, grandfather of Charles Darwin and pioneering naturalist.",
            website: "https://www.erasmusdarwin.org/",
            openingHours: "Thu-Sun: 11am - 4pm",
            admissionFee: "Adults £5, Children Free",
            hasGaussianSplat: false,
            audioUrl: "assets/audio/erasmus-darwin/guide.mp3",
            audioDuration: "2:45",
            images: ["3310b11", "7561ca2"]
        },
        {
            id: 5,
            name: "Minster Pool",
            lat: 52.68425888409815,
            lng: -1.8296241752878086,
            category: "Landmark",
            description: "Picturesque pool adjacent to the Cathedral, created in the 12th century.",
            website: null,
            openingHours: "Open 24/7",
            admissionFee: "Free",
            hasGaussianSplat: false,
            audioUrl: "assets/audio/minster-pool/guide.mp3",
            audioDuration: "1:50",
            images: ["309f9b", "ffdce1d"]
        },
        {
            id: 6,
            name: "Market Square",
            lat: 52.68364822025759,
            lng: -1.8277362182034054,
            category: "Shopping",
            description: "Historic market square hosting regular markets since medieval times.",
            website: "https://www.lichfielddc.gov.uk/markets",
            openingHours: "Market Days: Tue, Fri, Sat",
            admissionFee: "Free",
            hasGaussianSplat: false,
            audioUrl: "assets/audio/market-square/guide.mp3",
            audioDuration: "2:15",
            images: ["49c068", "081999"]
        }
    ],
    
    // Partner locations (cafes with discounts)
    partners: [
        {
            id: 7,
            name: "Melbourne Coffee",
            lat: 52.68334370698809,
            lng: -1.8307704483412255,
            category: "Partner",
            description: "Independent specialty coffee shop serving Hundred House beans. Quirky atmosphere with pop art, dog-friendly, and famous for turmeric lattes and homemade cakes. Show this screen for your exclusive tourist discount!",
            discount: "10% off your entire order",
            discountCode: "LICHFIELDCODE10",
            openingHours: "Mon-Thu: 7:30am-4pm, Fri-Sat: 7:30am-5pm, Sun: 8am-4pm",
            phone: "+44 1543 416454",
            website: "https://www.melbourneinlichfield.com",
            images: ["72f39c", "1304b6f"]
        },
        {
            id: 8,
            name: "The Hub at St Mary's",
            lat: 52.68354841167994,
            lng: -1.8275223001329541,
            category: "Partner",
            description: "Lichfield's first Social Enterprise coffee shop in a beautiful converted church. Community space with gallery, dog-friendly, family-friendly with Mini Hub play area. Every purchase supports community outreach. Show this screen for your exclusive tourist discount!",
            discount: "10% off food and drinks",
            discountCode: "LICHFIELDCODE10",
            openingHours: "Mon-Sat: 9:30am-4pm, Sun: Closed",
            phone: "+44 1543 256611",
            website: "https://thehubstmarys.co.uk",
            hasGaussianSplat: true,
            splatFile: "stmarys.splat",
            images: ["stmarys/stmary1", "stmarys/stmary2"]
        },
        {
            id: 9,
            name: "Hindley's Bakery",
            lat: 52.68365324708457,
            lng: -1.8262274817117865,
            category: "Partner",
            description: "Traditional family bakery established 1893. Fourth-generation bakers crafting artisan bread, famous pork pies, sourdough, and homemade cakes daily. Cozy café at the back. Show this screen for your exclusive tourist discount!",
            discount: "10% off bakery items",
            discountCode: "LICHFIELDCODE10",
            openingHours: "Mon: 9am-3:30pm, Tue-Thu: 8:30am-3:30pm, Fri: 8:30am-4pm, Sat: 8am-4pm, Sun: Closed",
            phone: "+44 1543 416454",
            website: "https://hindleys-bakers.co.uk",
            images: ["3e6700", "a64604"]
        }
    ],
    
    // Bus stop location
    busStop: {
        id: 999,
        name: "🚌 Tour Bus Pickup Point",
        lat: 52.68136359225002,
        lng: -1.8256883108726254,
        category: "BusStop",
        description: "Your tour bus will depart from here. Please return 5 minutes before departure time.",
        departureTime: "17:30", // This will come from database later
        whatsappNumber: "+447912345678", // Made-up number as requested
        website: null,
        openingHours: null,
        admissionFee: null
    },
    
    // Visit Lichfield brand pink (RGB: 185, 47, 123)
    visitLichfieldPink: '#B92F7B',
    
    // Walking routes
    routes: [
        {
            id: 1,
            name: "Historic Loop",
            difficulty: "Moderate",
            duration: "60 min",
            distance: "2.0 km",
            landmarks: [6, 2, 5, 4, 1],
            color: "#E91E63",
            waypoints: [
                [52.683750395181505, -1.8274908371741143],
                [52.683594299615926, -1.8279173083894862],
                [52.68391556569423, -1.8285439615812014],
                [52.683949330174926, -1.8288329865964075],
                [52.684042410500076, -1.828814922532957],
                [52.68428971119006, -1.8291506130631323],
                [52.68450142136456, -1.8290873888410555],
                [52.684344463919786, -1.8293553391155697],
                [52.683916964639224, -1.8303352615301758],
                [52.68372361143372, -1.8314338759597535],
                [52.68486107055406, -1.8328349624917628],
                [52.68473038102862, -1.8326961590557675],
                [52.685011452550896, -1.831635937067055],
                [52.68551272209593, -1.8294889136190415],
                [52.68396438885225, -1.8273645295392138],
                [52.683747761821074, -1.8275151460390744]
            ],
            googleMapsUrl: "https://www.google.com/maps/dir/52.683750395181505,-1.8274908371741143/52.683594299615926,-1.8279173083894862/52.684344463919786,-1.8293553391155697/52.68486107055406,-1.8328349624917628/52.685011452550896,-1.831635937067055/52.683747761821074,-1.8275151460390744/@52.6840,-1.8300,15z/data=!4m2!4m1!3e2",
            appleMapsUrl: "https://maps.apple.com/?saddr=52.683750395181505,-1.8274908371741143&daddr=52.683747761821074,-1.8275151460390744&dirflg=w"
        },
        {
            id: 2,
            name: "Historic + Nature Route",
            difficulty: "Hard",
            duration: "90 min",
            distance: "3.2 km",
            landmarks: [6, 2, 5, 3, 4, 1],
            color: "#E91E63",
            waypoints: [
                [52.683750395181505, -1.8274908371741143],
                [52.683594299615926, -1.8279173083894862],
                [52.68391556569423, -1.8285439615812014],
                [52.683949330174926, -1.8288329865964075],
                [52.684042410500076, -1.828814922532957],
                [52.68428971119006, -1.8291506130631323],
                [52.68450142136456, -1.8290873888410555],
                [52.684344463919786, -1.8293553391155697],
                [52.683916964639224, -1.8303352615301758],
                [52.68372361143372, -1.8314338759597535],
                [52.684121867188175, -1.8321294918515594],
                [52.68358762420692, -1.833725413580714],
                [52.68422501102588, -1.8345408051112815],
                [52.6839583503311, -1.8353615610598788],
                [52.68429330192594, -1.8359516470377224],
                [52.685168066301955, -1.835334738970409],
                [52.685317654502235, -1.8347661749287891],
                [52.68594252318223, -1.8336546314793822],
                [52.68486107055406, -1.8328349624917628],
                [52.68473038102862, -1.8326961590557675],
                [52.685011452550896, -1.831635937067055],
                [52.68551272209593, -1.8294889136190415],
                [52.68396438885225, -1.8273645295392138],
                [52.683747761821074, -1.8275151460390744]
            ],
            googleMapsUrl: "https://www.google.com/maps/dir/52.683750395181505,-1.8274908371741143/52.683594299615926,-1.8279173083894862/52.684344463919786,-1.8293553391155697/52.68429330192594,-1.8359516470377224/52.68486107055406,-1.8328349624917628/52.685011452550896,-1.831635937067055/52.683747761821074,-1.8275151460390744/@52.6845,-1.8310,15z/data=!4m2!4m1!3e2",
            appleMapsUrl: "https://maps.apple.com/?saddr=52.683750395181505,-1.8274908371741143&daddr=52.683747761821074,-1.8275151460390744&dirflg=w"
        }
    ],
    
    // Category colors
    categoryColors: {
        'Historic': '#B92F7B',
        'Museum': '#B92F7B',
        'Park': '#B92F7B',
        'Shopping': '#B92F7B',
        'Landmark': '#B92F7B',
        'Partner': '#D97706',  // Orange for partner cafes
        'BusStop': '#DC2626'   // Red for bus stop
    }
};
