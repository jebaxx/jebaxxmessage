< ? p h p 
 r e q u i r e _ o n c e ( _ _ D I R _ _ . " / v e n d o r / a u t o l o a d . p h p " ) ; 
 
 u s e   \ L I N E \ L I N E B o t \ H T T P C l i e n t \ C u r l H T T P C l i e n t ; 
 u s e   \ L I N E \ L I N E B o t ; 
 u s e   \ L I N E \ L I N E B o t \ R e s p o n s e ; 
 u s e   \ L I N E \ L I N E B o t \ M e s s a g e B u i l d e r \ T e x t M e s s a g e B u i l d e r ; 
 u s e   \ L I N E \ L I N E B o t \ C o n s t a n t \ H T T P H e a d e r ; 
 
 r e q u i r e _ o n c e ( " g o o g l e / a p p e n g i n e / a p i / c l o u d _ s t o r a g e / C l o u d S t o r a g e T o o l s . p h p " ) ; 
 u s e   g o o g l e \ a p p e n g i n e \ a p i \ c l o u d _ s t o r a g e \ C l o u d S t o r a g e T o o l s ; 
 
 $ g s _ p r e f i x   =   " g s : / / "   .   C l o u d S t o r a g e T o o l s : : g e t D e f a u l t G o o g l e S t o r a g e B u c k e t N a m e ( )   .   " / " ; 
 $ g s _ t o m o _ c s v   =   $ g s _ p r e f i x   .   " t o m o d a c h i _ p r o f i l e . c s v " ; 
 
 
 i f   ( ! a r r a y _ k e y _ e x i s t s ( ' L i n e _ i d ' ,   $ _ P O S T )   | |   ( ! a r r a y _ k e y _ e x s i s t s ( ' m e s s a g e ' ,   $ _ P O S T ) ) )   { 
         s y s l o g ( L O G _ E R R ,   " I l l e g a l   m e s s a g e   a r r i v e d   t o   p u s h   m e s s a g e   r e q u e s t   p o i n t . " ) ; 
 } 
 e l s e   { 
 
         i n c l u d e ( _ _ D I R _ _ . " / a c c e s s t o k e n . p h p " ) ; 
 
         / /   c r e a t e   H T T P C l i e n t   i n s t a n c e 
         $ h t t p C l i e n t   =   n e w   C u r l H T T P C l i e n t ( A C C E S S _ T O K E N ) ; 
         $ B o t   =   n e w   L I N E B o t ( $ h t t p C l i e n t ,   [ ' c h a n n e l S e c r e t '   = >   S E C R E T _ T O K E N ] ) ; 
 
         / / 
         / /   B R O A D C A S T k0�[�_Y0�0L00]0n0��n0�[a��o00t o m o d a c h i _ p r o f i l e k0{v2�n0n0I D k0j0�0
         / / 
         i f   ( $ _ P O S T [ ' L i n e _ i d ' ]   = =   ' B R O A D C A S T ' )   { 
 
         	 i f   ( ( $ r _ h n d l   =   f o p e n ( $ g s _ t o m o _ c s v ,   " r " ) )   = =   F A L S E )   { 
 	         s y s l o g ( L O G _ E R R ,   " t o m o d a c h i   f i l e   c a n n o t   o p e n " ) ; 
 	         r e t u r n ; 
 	 } 
 
 	 $ p u s h M e s s a g e B u i l d e r   =   n e w   T e x t M e s s a g e B u i l d e r ( $ _ P O S T [ ' m e s s a g e ' ] ) ; 
 	 $ r e s p o n s e   =   $ B o t - > p u s h M e s s a g e ( $ l i n e I d ,   $ p u s h M e s s a g e B u i l d e r ) ; 
 
 	 w h i l e   ( 1 )   { 
 	         i f   ( ( $ p r o f i l e _ l i n e   =   f g e t c s v ( $ r _ h n d l ) )   = =   F A L S E )   b r e a k ; 
 
 	         $ r e s p o n s e   =   $ B o t - > p u s h M e s s a g e ( $ p r o f i l e _ l i n e [ 0 ] ,   $ p u s h M e s s a g e B u i l d e r ) ; 
 	         i f   ( $ r e s p o n s e - > g e t H T T P S t a t u s ( )   ! =   2 0 0 )   { 
 	 	 s y s l o g ( L O G _ E R R ,   " f a i l e d   t o   s e n d i n g   a   p u s h   m e s s a g e   a n d   s t a t u s   c o d e   i s   " .   $ r e s p o n s e - > g e t H T T P S t a t u s ( )   .   "   a n d   i d   =   " . $ p r o f i l e _ l i n e [ 0 ] ) ; 
 	         } 
 	 } 
 	 f c l o s e ( $ r _ h n d l ) ; 
 
         } 
         e l s e   { 
 	 $ p u s h M e s s a g e B u i l d e r   =   n e w   T e x t M e s s a g e B u i l d e r ( $ _ P O S T [ ' m e s s a g e ' ] ) ; 
 	 $ r e s p o n s e   =   $ B o t - > p u s h M e s s a g e ( $ _ P O S T [ ' l i n e I d ' ] ,   $ p u s h M e s s a g e B u i l d e r ) ; 
 
 	 i f   ( $ r e s p o n s e - > g e t H T T P S t a t u s ( )   ! =   2 0 0 )   { 
 	         s y s l o g ( L O G _ E R R ,   " f a i l e d   t o   s e n d i n g   a   p u s h   m e s s a g e   a n d   s t a t u s   c o d e   i s   " .   $ r e s p o n s e - > g e t H T T P S t a t u s ( ) ) ; 
 	 } 
         } 
 } 
 
 
 f u n c t i o n   P u s h M e s s a g e ( $ L i n e _ i d ,   $ m e s s a g e )   { 
 
         / /   c r e a t e   H T T P C l i e n t   i n s t a n c e 
         $ h t t p C l i e n t   =   n e w   C u r l H T T P C l i e n t ( A C C E S S _ T O K E N ) ; 
         $ B o t   =   n e w   L I N E B o t ( $ h t t p C l i e n t ,   [ ' c h a n n e l S e c r e t '   = >   S E C R E T _ T O K E N ] ) ; 
 
         $ p u s h M e s s a g e B u i l d e r   =   n e w   T e x t M e s s a g e B u i l d e r ( $ m e s s a g e ) ; 
         $ r e s p o n s e   =   $ B o t - > p u s h M e s s a g e ( $ l i n e I d ,   $ p u s h M e s s a g e B u i l d e r ) ; 
 
         i f   ( $ r e s p o n s e - > g e t H T T P S t a t u s ( )   ! =   2 0 0 )   { 
 	 s y s l o g ( L O G _ E R R ,   " f a i l e d   t o   s e n d i n g   a   p u s h   m e s s a g e   a n d   s t a t u s   c o d e   =   " .   $ r e s p o n s e - > g e t H T T P S t a t u s ( ) ) ; 
         } 
 } 
 
 
 ? > 
 